<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command;

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Request;
use PhpSpec\Ai\Agent\Step;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Pair\SubprocessRunner;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 * CLI command that uses AI to suggest what to describe (spec or feature) next.
 *
 * Scans the project's source, spec, and feature files, sends the project
 * context to the LLM, and displays a suggestion for the next BDD step.
 * Offers to create the suggested artifact.
 */
final class Next extends Command
{
    private Filesystem $filesystem;

    private readonly SpecRunner $specRunner;

    /** @var (callable(array{provider: string, model?: string, api_key: string}, string): array{type: string, target: string, reason: string})|null */
    private $suggestFn;

    /**
     * @param Configuration $config
     * @param Filesystem|null $filesystem
     * @param (callable(array{provider: string, model?: string, api_key: string}, string): array{type: string, target: string, reason: string})|null $suggestFn injectable display seam; receives the AI config and the rendered situation
     * @param SpecRunner|null $specRunner runs the suite for feature grounding; defaults to a subprocess
     * @param ProviderInterface|null $provider injectable AI seam for the agent pipeline
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        ?callable $suggestFn = null,
        ?SpecRunner $specRunner = null,
        private readonly ?ProviderInterface $provider = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->suggestFn = $suggestFn;
        $this->specRunner = $specRunner ?? new SubprocessRunner();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('next')
            ->setDescription('AI suggests what to describe or specify next');
    }

    protected function execute(Input $input, Output $output): int
    {
        // 1. Check AI config
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $output->writeln('<fg=red>AI configuration required. Add an "ai" section to your phpspec config.</>');
            return 1;
        }

        // 2. Load bootstrap
        if (!$this->loadBootstrap()) {
            $output->writeln('<fg=red>Bootstrap file not found.</>');
            return 1;
        }

        // 3. Scan project and get suggestion
        $output->writeln('');
        $output->writeln('  <fg=gray>Analysing project...</>');
        $output->writeln('');

        $suggestion = $this->getSuggestion($aiConfig);

        // 4. Validate — if we got no target and no reason, the response was unusable
        if ($suggestion['target'] === '' && $suggestion['reason'] === '') {
            $output->writeln('  <fg=yellow>Could not get a suggestion. Please try again.</>');
            $output->writeln('');
            return 1;
        }

        // 4b. If the suggested spec already exists, never re-describe it (that
        // just loops) — coach to run so the missing class is driven out instead.
        if ($suggestion['type'] === 'spec' && $this->specFileExists($suggestion['target'])) {
            $output->writeln(sprintf('  A spec for <fg=bright-blue;options=bold>%s</> already exists.', $suggestion['target']));
            $output->writeln('  <fg=gray>Run it to drive out the class:</> ' . self::invokedBinary() . ' run');
            $output->writeln('');

            return 0;
        }

        // 4c. Red, green, REFACTOR happens once: a refactor suggestion for a
        // target unchanged since the last recorded refactoring steers to
        // growth instead of polishing the same class forever.
        $suggestion = $this->steerAwayFromStaleRefactor($suggestion);

        // 5. Display
        $this->displaySuggestion($output, $suggestion);

        // 6. Offer to create (only when we have a proper target)
        if ($suggestion['target'] === '') {
            return 0;
        }

        if ($suggestion['type'] === 'spec' || $suggestion['type'] === 'feature') {
            return $this->offerDescribe($input, $output, $suggestion['target']);
        }

        if ($suggestion['type'] === 'example') {
            return $this->offerExemplify($input, $output, $suggestion['target']);
        }

        if ($suggestion['type'] === 'refactor') {
            return $this->offerRefactor($output, $suggestion['target']);
        }

        return 0;
    }

    /**
     * Whether a spec file already exists for the given class, so `next` never
     * suggests describing a spec that is already there.
     */
    private function specFileExists(string $fqcn): bool
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $file = getcwd() . DIRECTORY_SEPARATOR . $specPath . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $fqcn) . $this->config->getSpecSuffix();

        return $this->filesystem->exists($file);
    }

    /**
     * The suggestion for what to build next, through the agent pipeline: the
     * suite grounding is seeded from a real run, the current TDD step resolves
     * deterministically, and the model answers with a suggest_next tool call
     * (never JSON-in-prose to parse).
     *
     * @param array{provider: string, model?: string, api_key: string} $aiConfig
     * @return array{type: string, target: string, reason: string}
     */
    private function getSuggestion(array $aiConfig): array
    {
        // Feature (story) state grounds the suggestion so `next` can favour
        // features when they are present; it is null for a spec-only project.
        $grounding = $this->featureGrounding();

        if ($this->suggestFn !== null) {
            $situation = $grounding === null ? '' : Request::suiteText($grounding);

            return ($this->suggestFn)($aiConfig, $situation);
        }

        // The grounded cases need no imagination: when the suite state
        // determines the step and its subject, the suggestion IS the step.
        $suggestion = self::suggestionFromStep(Step::resolve('', $grounding ?? Grounding::empty()));
        if ($suggestion !== null) {
            return $suggestion;
        }

        $agent = new Agent($this->config, $this->filesystem, $this->provider);
        $outcome = $agent->do(CommandProfile::load('next'), '', $grounding);

        $reason = (string) ($outcome->data['reason'] ?? '');

        return [
            'type' => (string) ($outcome->data['type'] ?? 'info'),
            'target' => (string) ($outcome->data['target'] ?? ''),
            'reason' => $reason !== '' ? $reason : $outcome->prose,
        ];
    }

    /**
     * @param array{type: string, target: string, reason: string} $suggestion
     */
    private function displaySuggestion(Output $output, array $suggestion): void
    {
        $typeLabel = match ($suggestion['type']) {
            'spec' => 'Describe a spec for',
            'feature' => 'Write a feature scenario for',
            'example' => 'Add an example to',
            'refactor' => 'Refactor',
            default => '',
        };

        if ($typeLabel !== '' && $suggestion['target'] !== '') {
            $output->writeln("  <options=bold>$typeLabel</> <fg=bright-blue;options=bold>{$suggestion['target']}</>");
        }

        if ($suggestion['reason'] !== '') {
            $output->writeln('');
            foreach (explode("\n", wordwrap($suggestion['reason'], 76)) as $line) {
                $output->writeln("  <fg=gray>$line</>");
            }
        }

        $output->writeln('');
    }

    private function offerDescribe(Input $input, Output $output, string $target): int
    {
        $classArg = str_replace('\\', '/', $target);

        if (!$input->isInteractive()) {
            $output->writeln('  <fg=gray>Run:</> ' . self::invokedBinary() . " describe $classArg");
            return 0;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '  <fg=yellow>Would you like me to create that for you?</> [Y/n] ',
            true,
        );

        if (!$helper->ask($input, $output, $question)) {
            return 0;
        }

        $application = $this->getApplication();
        if ($application === null) {
            return 1;
        }

        return $application->find('describe')->run(
            new ArrayInput(['class' => $classArg]),
            $output,
        );
    }

    private function offerExemplify(Input $input, Output $output, string $target): int
    {
        $classArg = str_replace('\\', '/', $target);

        $output->writeln('  <fg=gray>Run:</> ' . self::invokedBinary() . " exemplify $classArg <method>");

        return 0;
    }

    /**
     * Replaces a refactor suggestion with a growth steer when the journal
     * shows its target already polished and unchanged since: red, green,
     * REFACTOR happens once per change. A backstop; the model is told the
     * same journal up front, so it normally proposes growth itself.
     *
     * @param array{type: string, target: string, reason: string} $suggestion
     * @return array{type: string, target: string, reason: string}
     */
    private function steerAwayFromStaleRefactor(array $suggestion): array
    {
        if ($suggestion['type'] !== 'refactor' || $suggestion['target'] === '') {
            return $suggestion;
        }

        $srcDir = getcwd() . '/' . ltrim($this->config->getSrcPath(), './');
        $polished = (new RefactorJournal($this->filesystem))->unchangedTargets($srcDir);
        if (!in_array($suggestion['target'], $polished, true)) {
            return $suggestion;
        }

        return [
            'type' => 'info',
            'target' => '',
            'reason' => sprintf('"%s" was already refactored and has not changed since. Grow the story instead: add the next scenario or feature.', $suggestion['target']),
        ];
    }

    /**
     * The green-suite step: point at the refactor command for the suggested
     * target. A hint, not a run, because refactoring starts with a baseline
     * the human should see.
     */
    private function offerRefactor(Output $output, string $target): int
    {
        $classArg = str_replace('\\', '/', $target);

        $output->writeln('  <fg=gray>Run:</> ' . self::invokedBinary() . " refactor $classArg");

        return 0;
    }

    /**
     * The phpspec invocation as the user typed it, so a hint points at a
     * binary that exists in their install (vendor/bin/phpspec, bin/phpspec,
     * or a global phpspec), never at a hardcoded path.
     */
    private static function invokedBinary(): string
    {
        $argv0 = $_SERVER['argv'][0] ?? '';

        return is_string($argv0) && $argv0 !== '' ? $argv0 : 'phpspec';
    }

    /**
     * The suggestion a resolved step determines on its own: undefined steps to
     * write, a failing example to make green, or a pending gap to fill need no
     * imagination, so no model call and no latency. Null for the open-ended
     * states (empty project, everything green) where the model's suggestion is
     * the value.
     *
     * @return array{type: string, target: string, reason: string}|null
     */
    private static function suggestionFromStep(?Step $step): ?array
    {
        if ($step === null || $step->subject === null) {
            return null;
        }

        return match ($step->phase) {
            Phase::WriteSteps => ['type' => 'info', 'target' => $step->subject, 'reason' => ucfirst($step->because) . '. Write the steps.'],
            Phase::WriteCode => ['type' => 'info', 'target' => $step->subject, 'reason' => ucfirst($step->because) . '. Make it green.'],
            Phase::WriteSpec => ['type' => 'example', 'target' => $step->subject, 'reason' => ucfirst($step->because) . '.'],
            default => null,
        };
    }

    /**
     * The live feature (story) grounding for the suggestion: a run of the whole
     * suite (--all) plus the last-touched feature and source. Null for a
     * spec-only project (no features dir, or a run that executed no feature),
     * so `next` keeps its spec-only behaviour then.
     */
    private function featureGrounding(): ?Grounding
    {
        $featuresDir = getcwd() . '/' . trim($this->config->getFeaturesPath(), './');
        if (!$this->filesystem->exists($featuresDir) || !$this->filesystem->isDir($featuresDir)) {
            return null;
        }

        $summary = $this->specRunner->run('--all', new BufferedOutput())?->summary;
        if ($summary === null || !$summary->hasFeatures()) {
            return null;
        }

        $recency = new RecencyScanner($this->filesystem);

        return new Grounding(
            $summary,
            $recency->mostRecentFeature($featuresDir),
            $recency->mostRecentSource(getcwd() . '/' . ltrim($this->config->getSrcPath(), './')),
        );
    }

    private function loadBootstrap(): bool
    {
        $bootstrap = $this->config->getBootstrap() ?? (file_exists('vendor/autoload.php') ? 'vendor/autoload.php' : null);
        if ($bootstrap === null) {
            return true;
        }
        if (!file_exists($bootstrap)) {
            return false;
        }
        require_once $bootstrap;
        return true;
    }
}
