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

use DOMException;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\Console\Command\Run\CoverageReporter;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Extensions\FormatterBridge;
use PhpSpec\FilterRegistry;
use PhpSpec\LineTargetRegistry;
use PhpSpec\Loader;
use PhpSpec\Parallel\ParallelRunner;
use PhpSpec\Report\Formatter;
use PhpSpec\Report\Formatter\Dot;
use PhpSpec\Report\Formatter\Junit;
use PhpSpec\Report\Formatter\Pretty;
use PhpSpec\Report\Formatter\Tap;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use PhpSpec\Runner;
use PhpSpec\StopConditions;
use PhpSpec\TitleFilter;
use Random\RandomException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * CLI command that runs specs and features, orchestrating the full lifecycle: bootstrap loading,
 * spec loading, execution, result formatting, coverage collection, and interactive code generation.
 */
final class Run extends Command
{
    /**
     * @param Loader $loader the spec/feature file loader
     * @param Runner $runner the spec runner
     * @param Configuration $config the project configuration
     */
    public function __construct(
        private readonly Loader $loader,
        private readonly Runner $runner,
        private readonly Configuration $config = new Configuration('.'),
        private readonly ?ExtensionLoader $extensionLoader = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDefinition([
                new Argument(
                    'files',
                    Argument::OPTIONAL | Argument::IS_ARRAY,
                    'Spec/feature paths to run',
                ),
            ])
            ->addOption('stop-on-failure', null, Option::VALUE_NONE, 'Stop on first failure or error')
            ->addOption('stop-on-error', null, Option::VALUE_NONE, 'Stop on first error')
            ->addOption('stop-on-warning', null, Option::VALUE_NONE, 'Stop on first warning')
            ->addOption('stop-on-deprecation', null, Option::VALUE_NONE, 'Stop on first deprecation')
            ->addOption('stop-on-notice', null, Option::VALUE_NONE, 'Stop on first notice')
            ->addOption('stop-on-skipped', null, Option::VALUE_NONE, 'Stop on first skipped example')
            ->addOption('stop-on-problems', null, Option::VALUE_NONE, 'Stop on any non-pass result')
            ->addOption('filter', null, Option::VALUE_REQUIRED, 'Run only specs matching pattern')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format (pretty, dot, tap, junit)', 'pretty')
            ->addOption('order', null, Option::VALUE_REQUIRED, 'Run order (default, random)', 'default')
            ->addOption('seed', null, Option::VALUE_REQUIRED, 'Seed for random order')
            ->addOption('profile', null, Option::VALUE_OPTIONAL, 'Show N slowest examples', false)
            ->addOption('bootstrap', 'b', Option::VALUE_REQUIRED, 'PHP file to require before running specs')
            ->addOption('all', null, Option::VALUE_NONE, 'Run all suites (specs and features)')
            ->addOption('story', null, Option::VALUE_NONE, 'Run only features (story BDD)')
            ->addOption('fake', null, Option::VALUE_NONE, 'Generate method bodies with hardcoded return values from specs')
            ->addOption('coverage', null, Option::VALUE_NONE, 'Generate code coverage report')
            ->addOption('coverage-clover', null, Option::VALUE_REQUIRED, 'Generate Clover XML coverage report to file')
            ->addOption('coverage-html', null, Option::VALUE_REQUIRED, 'Generate HTML coverage report to directory')
            ->addOption('coverage-min', null, Option::VALUE_REQUIRED, 'Fail if coverage is below this percentage')
            ->addOption('parallel', null, Option::VALUE_OPTIONAL, 'Run specs in parallel processes (--parallel=N for N workers)', false)
            ->setDescription('Runs specifications');
    }

    /**
     * Orchestrates the full spec/feature run lifecycle.
     *
     * 1. Loads the bootstrap file (--bootstrap, phpspec.json, or vendor/autoload.php).
     *    Exits early with code 1 if the specified bootstrap file does not exist.
     *
     * 2. Registers PSR-4 autoloaders defined in the "autoload" config key so that
     *    classes under test can be resolved without Composer.
     *
     * 3. Starts code coverage collection if any --coverage* option was given.
     *    Returns early with code 1 if Xdebug/PCOV is not available.
     *
     * 4. Loads and runs the suite: discovers spec/feature files via the Loader,
     *    applies --filter, --stop-on-failure, --order=random/--seed, then delegates
     *    execution to the Runner. Measures wall-clock duration.
     *
     * 5. Renders results through the selected formatter (pretty, dot, tap, junit),
     *    resolved from --format or the config file.
     *
     * 6. If --profile was given, prints the N slowest examples with their durations.
     *
     * 7. If coverage was collected, stops collection and renders reports (text,
     *    clover XML, HTML). Enforces --coverage-min threshold, returning code 2
     *    if coverage is below the minimum.
     *
     * 8. Runs interactive code generation: scans results for missing types, undefined
     *    methods, undefined steps, and fakeable methods. Prompts the user to generate
     *    specs, classes, interfaces, method stubs, and step definitions. In --fake mode,
     *    fills empty method bodies with hardcoded return values from spec expectations.
     *
     * 9. Returns the suite exit code (0 = all passed, 1 = failures/errors).
     *
     * @param Input $input the console input (arguments and options)
     * @param Output $output the console output for writing results
     * @return int exit code: 0 = success, 1 = failure/error or bootstrap missing, 2 = coverage below minimum
     *
     * @throws RandomException
     * @throws DOMException
     */
    protected function execute(Input $input, Output $output): int
    {
        if (!$this->loadBootstrap($input, $output)) {
            return 1;
        }
        $this->registerAutoloader();

        $coverageReporter = $this->startCoverage($input, $output);

        if ($coverageReporter === false) {
            return 1;
        }

        $results = $this->runSuiteStreaming($input, $output);

        $this->printProfile($input, $output, $results);

        if ($coverageReporter) {
            $exitCode = $this->reportCoverage($input, $output, $coverageReporter);

            if ($exitCode !== null) {
                return $exitCode;
            }
        }

        if ($this->resolveFormat($input) !== 'junit') {
            $this->generateCode($output, $results, (bool) $input->getOption('fake'), $input->isInteractive());
        }

        return $results->status();
    }

    /**
     * Resolves and requires the bootstrap file from --bootstrap, config, or vendor/autoload.php.
     * Returns false if the specified file does not exist.
     *
     * @param Input $input the console input to read --bootstrap from
     * @param Output $output the console output for error messages
     * @return bool true if bootstrap loaded (or none needed), false if file not found
     */
    private function loadBootstrap(Input $input, Output $output): bool
    {
        $bootstrap = $input->getOption('bootstrap') ?? $this->config->getBootstrap();
        if ($bootstrap === null && file_exists('vendor/autoload.php')) {
            $bootstrap = 'vendor/autoload.php';
        }
        if ($bootstrap === null) {
            return true;
        }
        if (!file_exists($bootstrap)) {
            $output->writeln("<fg=red>Bootstrap file not found: $bootstrap</>");
            return false;
        }
        require $bootstrap;
        return true;
    }

    private function registerAutoloader(): void
    {
        $this->config->registerAutoloaders();
    }

    /**
     * Starts code coverage collection if any --coverage* option was given.
     *
     * @param Input $input the console input to check coverage options
     * @param Output $output the console output for error messages
     * @return CoverageReporter|null|false reporter if started, null if not requested, false on error
     */
    private function startCoverage(Input $input, Output $output): CoverageReporter|null|false
    {
        $wantsCoverage = $input->getOption('coverage')
            || $input->getOption('coverage-clover')
            || $input->getOption('coverage-html')
            || $input->getOption('coverage-min') !== null;

        if (!$wantsCoverage) {
            return null;
        }

        $reporter = new CoverageReporter();
        if (!$reporter->start($output)) {
            return false;
        }
        return $reporter;
    }

    /**
     * Loads spec/feature files, streams results through the formatter as they complete,
     * and returns the aggregated suite results.
     *
     * @param Input $input the console input for files, filter, order, and format options
     * @param Output $output the console output for displaying results
     * @return SuiteResult the aggregated suite results
     *
     * @throws RandomException
     */
    private function runSuiteStreaming(Input $input, Output $output): SuiteResult
    {
        $paths = $input->getArgument('files');

        // VALUE_OPTIONAL may swallow the positional arg: --parallel features → parallel="features"
        $parallel = $input->getOption('parallel');
        if ($parallel !== false && is_string($parallel) && !ctype_digit($parallel)) {
            $paths[] = $parallel;
            $input->setOption('parallel', null);
        }

        if (!empty($paths)) {
            $files = implode(',', $paths);
        } elseif ($input->getOption('story')) {
            $files = $this->config->getFeaturesPath();
        } elseif ($input->getOption('all')) {
            $suitePaths = $this->config->getAllLoadPaths();
            $files = str_contains($suitePaths, 'features') ? $suitePaths : $suitePaths . ',features/';
        } else {
            $files = $this->config->getAllLoadPaths();
        }
        $filter = $input->getOption('filter');

        if ($filter !== null) {
            FilterRegistry::activate(new TitleFilter($filter));
        }

        $suite = $this->loader->load($files, $filter);

        $problems = (bool) $input->getOption('stop-on-problems');
        $configStop = $this->config->getStopConditions();
        $stopOnFailure = $input->getOption('stop-on-failure') || $configStop->onFailure;
        $stop = new StopConditions(
            onFailure: $stopOnFailure || $problems,
            onError: $stopOnFailure || $input->getOption('stop-on-error') || $configStop->onError || $problems,
            onWarning: $input->getOption('stop-on-warning') || $configStop->onWarning || $problems,
            onDeprecation: $input->getOption('stop-on-deprecation') || $configStop->onDeprecation || $problems,
            onNotice: $input->getOption('stop-on-notice') || $configStop->onNotice || $problems,
            onSkipped: $input->getOption('stop-on-skipped') || $configStop->onSkipped || $problems,
        );

        $seed = null;
        if ($input->getOption('order') === 'random') {
            $seed = $input->getOption('seed') !== null ? (int) $input->getOption('seed') : random_int(0, 999999);
            $output->writeln("<fg=yellow>Randomised with seed $seed</>");
        }

        $formatter = $this->createFormatter($input, $output);
        $formatter->begin();

        $start = hrtime(true);
        $accumulated = [];

        $parallel = $input->getOption('parallel');
        if ($parallel !== false) {
            $workers = $parallel !== null ? (int) $parallel : null;
            $paths = [];
            foreach ($suite->getSpecifications() as $spec) {
                if (method_exists($spec, 'getPath')) {
                    $paths[] = $spec->getPath();
                }
            }
            $parallelRunner = new ParallelRunner($paths, $workers, $stop);
            $stream = $parallelRunner->stream();
        } else {
            $stream = $this->runner->stream($suite, $stop, $seed);
        }

        $lineTargeted = str_contains($files, ':');

        try {
            foreach ($stream as $result) {
                if (($filter !== null || $lineTargeted) && self::countLeaves($result) === 0) {
                    continue;
                }

                $formatter->printResult($result);
                $accumulated[] = $result;
            }
        } finally {
            FilterRegistry::reset();
            LineTargetRegistry::reset();
        }

        $results = new SuiteResult($accumulated);
        $results->setDuration((hrtime(true) - $start) / 1e9);
        $formatter->end($results);

        return $results;
    }

    /**
     * Counts example and step results in a result tree, so spec files whose
     * examples were all pruned by the title filter can be skipped entirely.
     *
     * @param Results $results the result tree to count
     * @return int the number of example/step leaves
     */
    private static function countLeaves(Results $results): int
    {
        $count = 0;

        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult || $result instanceof StepResult) {
                $count++;
            } elseif ($result instanceof Results) {
                $count += self::countLeaves($result);
            }
        }

        return $count;
    }

    /**
     * Creates the output formatter based on --format option or config.
     *
     * @param Input $input the console input for the --format option
     * @return string
     */
    private function resolveFormat(Input $input): string
    {
        $format = $input->getOption('format') !== 'pretty'
            ? $input->getOption('format')
            : $this->config->getFormat();

        return $format ?? 'pretty';
    }

    private function createFormatter(Input $input, Output $output): Formatter
    {
        $format = $this->resolveFormat($input);

        if ($this->extensionLoader !== null && $this->extensionLoader->hasFormatter($format)) {
            return new FormatterBridge($this->extensionLoader->getFormatter($format), $output);
        }

        return match ($format) {
            'dot' => new Dot($output),
            'tap' => new Tap($output),
            'junit' => new Junit($output),
            default => new Pretty($output),
        };
    }

    /**
     * Prints the N slowest examples when --profile is given (defaults to 10).
     *
     * @param Input $input the console input for the --profile option
     * @param Output $output the console output for writing the profile table
     * @param SuiteResult $results the suite results to extract slowest examples from
     */
    private function printProfile(Input $input, Output $output, SuiteResult $results): void
    {
        $profile = $input->getOption('profile');
        if ($profile === false) {
            return;
        }
        $count = $profile !== null ? (int) $profile : 10;
        $slowest = $results->getSlowestExamples($count);
        if (empty($slowest)) {
            return;
        }
        $output->writeln('');
        $output->writeln("Top $count slowest examples:");
        foreach ($slowest as $example) {
            $output->writeln(sprintf(
                '  <fg=yellow>%.4fs</> %s',
                $example->getDuration(),
                $example->getTitle(),
            ));
        }
    }

    /**
     * Stops coverage collection and renders reports. Returns an exit code if --coverage-min fails.
     *
     * @param Input $input the console input for coverage report options
     * @param Output $output the console output for report rendering
     * @param CoverageReporter $reporter the active coverage reporter
     * @return int|null exit code if coverage below minimum, null otherwise
     * @throws DOMException
     */
    private function reportCoverage(Input $input, Output $output, CoverageReporter $reporter): ?int
    {
        return $reporter->report(
            $output,
            $this->config->getSrcPath(),
            (bool) $input->getOption('coverage'),
            $input->getOption('coverage-clover'),
            $input->getOption('coverage-html'),
            $input->getOption('coverage-min'),
        );
    }

    /**
     * Delegates to CodeGenerator to interactively generate missing classes, interfaces,
     * methods, step definitions, and --fake return bodies based on suite results.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param SuiteResult $results the suite results to scan for generation candidates
     * @param bool $fake whether --fake mode is enabled
     */
    private function generateCode(Output $output, SuiteResult $results, bool $fake, bool $interactive = true): void
    {
        $srcPath = ltrim($this->config->getSrcPath(), './');
        $specPath = ltrim($this->config->getSpecPath(), './');
        $codeGenerator = new CodeGenerator($srcPath, $specPath, $interactive, $this->config->getSpecSuffix(), $this->config->getPsr4Prefix());
        $codeGenerator->generate($output, $results, $fake);
    }
}
