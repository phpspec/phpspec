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

use InvalidArgumentException;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Ai\SpecSubprocess;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\RefactorAgent;
use PhpSpec\Console\Command\Refactor\RefactorResult;
use PhpSpec\Console\Command\Refactor\RefactorTarget;
use PhpSpec\Console\Command\Refactor\TargetResolver;
use PhpSpec\Console\Command\Refactor\UnresolvedTargetException;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 * CLI command that performs AI-powered, behaviour-preserving refactorings.
 *
 * Resolves a target (class FQCN, FQCN::method, a spec file path, or with no
 * target the source modified last), verifies that its specs pass, then
 * delegates to the RefactorAgent for a single baby-step refactoring.
 */
final class Refactor extends Command
{
    private Filesystem $filesystem;

    /** @var (callable(string): array{0: int, 1: string})|null */
    private $specRunner;

    /** @var (callable(string, string, ?string): RefactorResult)|null */
    private $refactorFn;

    /**
     * @param Configuration $config
     * @param Filesystem|null $filesystem
     * @param (callable(string): array{0: int, 1: string})|null $specRunner
     * @param (callable(string, string, ?string): RefactorResult)|null $refactorFn
     * @param ProviderInterface|null $provider injectable AI seam for the agent
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        ?callable $specRunner = null,
        ?callable $refactorFn = null,
        private readonly ?ProviderInterface $provider = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->specRunner = $specRunner;
        $this->refactorFn = $refactorFn;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('refactor')
            ->setDescription('AI-powered behaviour-preserving refactoring')
            ->addArgument('target', Argument::OPTIONAL, 'Class FQCN, FQCN::method, or spec file path; the source modified last when left out');
    }

    protected function execute(Input $input, Output $output): int
    {
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $output->writeln('<fg=red>' . $this->config->aiConfigProblem() . '</>');
            return 1;
        }

        if (!$this->loadBootstrap()) {
            $output->writeln('<fg=red>Bootstrap file not found.</>');
            return 1;
        }

        $argument = $input->getArgument('target');

        try {
            $target = (new TargetResolver($this->config, $this->filesystem, getcwd() ?: '.'))->resolve(is_string($argument) ? $argument : null);
        } catch (UnresolvedTargetException $e) {
            $output->writeln('<fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
            return 1;
        }

        // The proposal is shown and confirmed BEFORE the write, so nothing
        // reaches disk unbidden.
        $displayed = false;
        $confirm = function (string $technique, string $description, string $diff) use ($input, $output, $target, &$displayed): bool {
            $displayed = true;
            $this->displayProposal($output, $technique, $description, $diff, $target->sourceFile);

            if (!$input->isInteractive()) {
                return true;
            }

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            return (bool) $helper->ask($input, $output, new ConfirmationQuestion('  <fg=yellow>Apply?</> [Y/n] ', true));
        };

        $refactor = $this->refactorFn;
        if ($refactor === null) {
            try {
                $provider = $this->provider ?? ProviderFactory::create($aiConfig);
            } catch (RuntimeException|InvalidArgumentException $e) {
                $output->writeln('<fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
                return 1;
            }

            $refactor = fn(string $source, string $spec, ?string $method): RefactorResult => $this->refactorWith($provider, $aiConfig, $source, $spec, $method, $confirm);
        }

        $output->writeln('  <fg=gray>Checking the specs...</>');
        [$exitCode, $specOutput] = $this->runSpecs($target->specFile);

        if ($exitCode !== 0) {
            $output->writeln('');
            $output->writeln('<fg=red>Specs must pass before refactoring.</> Fix failing specs first.');
            $output->writeln('');
            $output->writeln($specOutput);
            return 1;
        }

        $output->writeln('');
        $result = $refactor($target->sourceFile, $target->specFile, $target->method);

        return $this->displayResult($output, $result, $target, $displayed);
    }

    /**
     * Displays the proposed refactoring: the technique, why, and the diff.
     */
    private function displayProposal(Output $output, string $technique, string $description, string $diff, string $srcPath): void
    {
        $output->writeln('  <options=bold>' . OutputFormatter::escape($technique) . '</>');
        $output->writeln('  ' . OutputFormatter::escape(wordwrap($description, 76, "\n  ")));
        $output->writeln('');

        if ($diff !== '') {
            $relativeSrc = $this->relativePath($srcPath);
            $output->writeln("  <fg=white>$relativeSrc</>");
            $output->writeln($diff);
        }

        $output->writeln('');
    }

    /**
     * Displays the refactoring result and returns the exit code. When the
     * confirm hook already showed the proposal, it is not repeated.
     */
    private function displayResult(Output $output, RefactorResult $result, RefactorTarget $target, bool $alreadyDisplayed = false): int
    {
        if (!$result->success && $result->technique === 'None') {
            $output->writeln(sprintf('  <fg=yellow>Nothing to refactor in %s: %s</>', OutputFormatter::escape($target->fqcn), OutputFormatter::escape($result->description)));
            return 0;
        }

        if (!$alreadyDisplayed) {
            $this->displayProposal($output, $result->technique, $result->description, $result->diff, $target->sourceFile);
        }

        if (!$result->applied) {
            $output->writeln('  <fg=yellow>Not applied; the file is untouched.</>');

            return 0;
        }

        if ($result->success) {
            $output->writeln('  <fg=green>Specs still pass ✓</>');
            return 0;
        }

        $output->writeln('  <fg=red>Refactoring reverted, specs failed ✘</>');
        return 1;
    }

    /**
     * Asks the model for one refactoring of the target, remembering a kept
     * one so a later run neither undoes nor repeats it.
     *
     * @param array{provider: string, model?: string, api_key?: string, effort?: string} $aiConfig
     * @param callable(string, string, string): bool $confirm asked with (technique, description, diff) before the write
     */
    private function refactorWith(ProviderInterface $provider, array $aiConfig, string $source, string $spec, ?string $method, callable $confirm): RefactorResult
    {
        $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider']);
        $journal = new RefactorJournal($this->filesystem);

        $agent = new RefactorAgent($provider, $model, $this->filesystem, $aiConfig['effort'] ?? null, $this->specRunner, $confirm);
        $result = $agent->refactor($source, $spec, $method, $journal->rendered());

        if ($result->success && $result->applied && $result->technique !== 'None') {
            $journal->record($this->relativeClass($source), $result->technique, $result->description);
        }

        return $result;
    }

    /**
     * The class-ish name a source path denotes, for the journal (src/App/X.php
     * reads as App\X).
     */
    private function relativeClass(string $srcPath): string
    {
        $relative = $this->relativePath($srcPath);
        $srcDir = ltrim($this->config->getSrcPath(), './');
        if (str_starts_with($relative, $srcDir . '/')) {
            $relative = substr($relative, strlen($srcDir) + 1);
        }

        return str_replace('/', '\\', preg_replace('/\.php$/', '', $relative) ?? $relative);
    }

    /**
     * Runs specs for the given path via subprocess or injectable runner.
     *
     * @return array{0: int, 1: string}
     */
    private function runSpecs(string $specPath): array
    {
        if ($this->specRunner !== null) {
            return ($this->specRunner)($specPath);
        }

        return SpecSubprocess::run($specPath);
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

    private function relativePath(string $absPath): string
    {
        $cwd = getcwd() . '/';
        if (str_starts_with($absPath, $cwd)) {
            return substr($absPath, strlen($cwd));
        }
        return $absPath;
    }
}
