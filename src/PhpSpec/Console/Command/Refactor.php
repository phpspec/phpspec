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

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\SpecSubprocess;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\RefactorAgent;
use PhpSpec\Console\Command\Refactor\RefactorResult;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 * CLI command that performs AI-powered, behaviour-preserving refactorings.
 *
 * Resolves a target (class FQCN, FQCN::method, or spec file path), verifies
 * that specs pass, then delegates to the RefactorAgent for a single baby-step
 * refactoring.
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
            ->addArgument('target', Argument::REQUIRED, 'Class FQCN, FQCN::method, or spec file path');
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

        // 3. Resolve target
        $targetArg = $input->getArgument('target');
        if (!is_string($targetArg) || $targetArg === '') {
            $output->writeln('<fg=red>Could not resolve target. Provide a class FQCN, FQCN::method, or spec file path.</>');
            return 1;
        }

        [$srcPath, $specPath, $method] = $this->resolveTarget($targetArg);

        // 4. Verify files exist
        if (!$this->filesystem->exists($srcPath)) {
            $output->writeln("<fg=red>Source file not found: $srcPath</>");
            return 1;
        }

        if (!$this->filesystem->exists($specPath)) {
            $output->writeln("<fg=red>Spec file not found: $specPath</>");
            return 1;
        }

        // 5. Baseline spec run
        $output->writeln('  <fg=gray>Running baseline specs...</>');
        [$exitCode, $specOutput] = $this->runSpecs($specPath);

        if ($exitCode !== 0) {
            $output->writeln('');
            $output->writeln('<fg=red>Specs must pass before refactoring.</> Fix failing specs first.');
            $output->writeln('');
            $output->writeln($specOutput);
            return 1;
        }

        // 6. Run refactoring; the proposal is shown and confirmed BEFORE the
        // write, so nothing reaches disk unbidden.
        $output->writeln('  <fg=gray>Analysing code for refactoring opportunities...</>');
        $output->writeln('');

        $displayed = false;
        $confirm = function (string $technique, string $description, string $diff) use ($input, $output, $srcPath, &$displayed): bool {
            $displayed = true;
            $this->displayProposal($output, $technique, $description, $diff, $srcPath);

            if (!$input->isInteractive()) {
                return true;
            }

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            return (bool) $helper->ask($input, $output, new ConfirmationQuestion('  <fg=yellow>Apply this refactoring?</> [Y/n] ', true));
        };

        $result = $this->performRefactoring($aiConfig, $srcPath, $specPath, $method, $confirm);

        // 7. Display result
        return $this->displayResult($output, $result, $srcPath, $displayed);
    }

    /**
     * Displays the proposed refactoring: technique, description, and the diff.
     */
    private function displayProposal(Output $output, string $technique, string $description, string $diff, string $srcPath): void
    {
        $output->writeln("  <fg=white;options=bold>Refactoring technique:</> $technique");
        $output->writeln('');
        $output->writeln("  $description");
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
    private function displayResult(Output $output, RefactorResult $result, string $srcPath, bool $alreadyDisplayed = false): int
    {
        if (!$result->success && $result->technique === 'None') {
            $output->writeln("  <fg=yellow>$result->description</>");
            return 0;
        }

        if (!$alreadyDisplayed) {
            $this->displayProposal($output, $result->technique, $result->description, $result->diff, $srcPath);
        }

        if (!$result->applied) {
            $output->writeln('  <fg=yellow>Not applied; the file is untouched.</>');

            return 0;
        }

        if ($result->success) {
            $output->writeln('  <fg=green>Specs still pass ✓</>');
            return 0;
        }

        $output->writeln('  <fg=red>Refactoring reverted — specs failed ✗</>');
        return 1;
    }

    /**
     * Resolves a target string into [srcPath, specPath, method].
     *
     * Supports:
     * - `App\Calculator` → src/App/Calculator.php + spec/App/Calculator.spec.php
     * - `App\Calculator::sum` → same + method focus
     * - `spec/App/Calculator.spec.php` → infer src from spec
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    public function resolveTarget(string $target): array
    {
        $specSuffix = $this->config->getSpecSuffix();
        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');
        $cwd = getcwd();

        // Spec file path given
        if (str_ends_with($target, $specSuffix) || str_contains($target, '.spec.php')) {
            $specFile = str_starts_with($target, '/') ? $target : $cwd . '/' . $target;

            // Infer src from spec: spec/App/Foo.spec.php → src/App/Foo.php
            $relative = $target;
            if (str_starts_with($relative, $specPath . '/')) {
                $relative = substr($relative, strlen($specPath . '/'));
            }
            $relative = str_replace($specSuffix, '.php', $relative);
            $srcFile = $cwd . '/' . $srcPath . '/' . $relative;

            return [$srcFile, $specFile, null];
        }

        // FQCN::method
        $method = null;
        $fqcn = $target;
        if (str_contains($target, '::')) {
            [$fqcn, $method] = explode('::', $target, 2);
        }

        // FQCN → file paths
        $classPath = str_replace('\\', '/', $fqcn);
        $srcFile = $cwd . '/' . $srcPath . '/' . $classPath . '.php';
        $specFile = $cwd . '/' . $specPath . '/' . $classPath . $specSuffix;

        return [$srcFile, $specFile, $method];
    }

    /**
     * Performs the refactoring via injected callable or RefactorAgent.
     *
     * @param array{provider: string, model?: string, api_key: string, effort?: string} $aiConfig
     * @param callable(string, string, string): bool $confirm asked with (technique, description, diff) before the write
     */
    private function performRefactoring(array $aiConfig, string $srcPath, string $specPath, ?string $method, callable $confirm): RefactorResult
    {
        if ($this->refactorFn !== null) {
            return ($this->refactorFn)($srcPath, $specPath, $method);
        }

        try {
            $provider = $this->provider ?? ProviderFactory::create($aiConfig);
        } catch (\RuntimeException $e) {
            return new RefactorResult(
                success: false,
                technique: 'None',
                description: $e->getMessage(),
                diff: '',
                specOutput: '',
            );
        }

        $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider']);

        return (new RefactorAgent($provider, $model, $this->filesystem, $aiConfig['effort'] ?? null, $this->specRunner, $confirm))->refactor($srcPath, $specPath, $method);
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
