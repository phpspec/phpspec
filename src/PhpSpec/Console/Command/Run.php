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
use PhpSpec\Console\Command\Run\GenerationReport;
use PhpSpec\Coverage\CoverageOptions;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Extensions\FormatterBridge;
use PhpSpec\FilterRegistry;
use PhpSpec\LineTargetRegistry;
use PhpSpec\Loader;
use PhpSpec\Parallel\ParallelRunner;
use PhpSpec\Report\Formatter;
use PhpSpec\Report\Formatter\Dot;
use PhpSpec\Report\Formatter\Html;
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
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @internal
 * CLI command that runs specs and features, orchestrating the full lifecycle: bootstrap loading,
 * spec loading, execution, result formatting, coverage collection, and interactive code generation.
 */
final class Run extends Command
{
    /** @var array<int, string> partial coverage state files written by parallel workers */
    private array $coveragePartials = [];

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
            ->addOption('paths-from', null, Option::VALUE_REQUIRED, 'Read spec/feature paths to run from a file, one per line')
            ->addOption('format', 'f', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'Output format(s): pretty, dot, tap, junit, html; repeatable, pair each with -o', [])
            ->addOption('out', 'o', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'Report destination for the corresponding --format; "std" is the console', [])
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
            ->addOption('coverage-json', null, Option::VALUE_REQUIRED, 'Generate JSON coverage report with per-example detail, for mutation testing tools (experimental)')
            ->addOption('coverage-src', null, Option::VALUE_REQUIRED, 'Source directory to scope coverage reports to (overrides config src_path)')
            ->addOption('coverage-partial', null, Option::VALUE_REQUIRED, 'Dump raw coverage state to a file (internal, used by parallel workers)')
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
     * 5. Renders results through the selected formatter (pretty, dot, tap,
     *    junit, html), then writes any report files requested with --output-*,
     *    resolved from --format or the config file.
     *
     * 6. If --profile was given, prints the N slowest examples with their durations.
     *
     * 7. If coverage was collected, stops collection and renders reports (text,
     *    clover XML, HTML, JSON). In parallel runs, worker partial states are
     *    merged first. Enforces --coverage-min threshold, returning code 2
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

        $pathsFrom = $input->getOption('paths-from');

        if ($pathsFrom !== null && !is_file($pathsFrom)) {
            $output->writeln("<fg=red>Paths file not found: $pathsFrom</>");

            return 1;
        }

        $unknownFormats = $this->unknownFormats($input);

        if ($unknownFormats !== []) {
            $output->writeln('<fg=red>Unknown format: ' . implode(', ', $unknownFormats) . ' (available: pretty, dot, tap, junit, html)</>');

            return 1;
        }

        $coverageReporter = $this->startCoverage($input, $output);

        if ($coverageReporter === false) {
            return 1;
        }

        $results = $this->runSuiteStreaming($input, $output);

        $this->writeReportFiles($input, $output, $results);
        $this->printProfile($input, $output, $results);

        if ($coverageReporter) {
            $exitCode = $this->reportCoverage($input, $output, $coverageReporter);

            if ($exitCode !== null) {
                return $exitCode;
            }
        }

        // When pair mode spawned this run (it sets an env var naming a report
        // file), record the generation candidates there and let pair mode drive
        // the interactive generation in the REPL. Otherwise generate here as usual.
        $reportPath = GenerationReport::requestedPath();
        if ($reportPath !== null) {
            GenerationReport::write($reportPath, $this->codeGenerator(false)->scan($results));
        } elseif (!in_array($this->resolveFormat($input), ['junit', 'html'], true)) {
            $this->generateCode($output, $results, (bool) $input->getOption('fake'), $input->isInteractive());
        }

        return $results->status();
    }

    /**
     * Writes the report files requested with --format/-o pairs, each rendered
     * from the full suite results in addition to the console format, and
     * renders any additional console-bound formats.
     *
     * @param Input $input the console input for the --format and -o options
     * @param Output $output the console output for the confirmation notes
     * @param SuiteResult $results the aggregated suite results
     */
    private function writeReportFiles(Input $input, Output $output, SuiteResult $results): void
    {
        $outputs = $this->resolveOutputs($input);

        foreach ($outputs['files'] as [$format, $file]) {
            $dir = dirname($file);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $handle = fopen($file, 'w');

            if ($handle === false) {
                $output->writeln("<fg=red>Could not write report to $file</>");
                continue;
            }

            $this->createFormatter($format, new StreamOutput($handle, StreamOutput::VERBOSITY_NORMAL, false))->format($results);
            fclose($handle);

            $output->writeln("  Report written to $file");
        }

        foreach ($outputs['extraConsole'] as $format) {
            $this->createFormatter($format, $output)->format($results);
        }
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
        if (!$this->wantsCoverage($input)) {
            return null;
        }

        // Parallel runs also collect per example: workers dump raw per-example
        // state and the parent merges it, which serves every report format.
        $perExample = $input->getOption('coverage-json') !== null
            || $input->getOption('coverage-partial') !== null
            || $input->getOption('parallel') !== false;

        $reporter = new CoverageReporter();

        if (!$reporter->start($output, perExample: $perExample)) {
            return false;
        }

        return $reporter;
    }

    /**
     * Checks whether any coverage option was given.
     *
     * @param Input $input the console input to check coverage options
     * @return bool true if any coverage report or the partial dump was requested
     */
    private function wantsCoverage(Input $input): bool
    {
        return $input->getOption('coverage')
            || $input->getOption('coverage-clover')
            || $input->getOption('coverage-html')
            || $input->getOption('coverage-json')
            || $input->getOption('coverage-partial')
            || $input->getOption('coverage-min') !== null;
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

        $pathsFrom = $input->getOption('paths-from');

        if ($pathsFrom !== null) {
            $listed = file($pathsFrom, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $paths = array_merge($paths, array_filter(array_map('trim', $listed ?: [])));
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

        $formatter = $this->createFormatter($this->resolveFormat($input), $output);
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

            $coveragePartialDir = $this->wantsCoverage($input)
                ? sys_get_temp_dir() . '/phpspec_coverage_' . uniqid()
                : null;

            // --config is defined at the application level, absent when the
            // command runs standalone (e.g. under CommandTester)
            $configPath = $input->hasOption('config') ? $input->getOption('config') : null;

            $parallelRunner = new ParallelRunner(
                $paths,
                $workers,
                $stop,
                coveragePartialDir: $coveragePartialDir,
                configPath: $configPath,
            );
            $stream = $parallelRunner->stream();
        } else {
            $parallelRunner = null;
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

        if ($parallelRunner !== null) {
            $this->coveragePartials = $parallelRunner->getCoveragePartials();
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
    /**
     * Resolves the --format/-o pairs into the console format and the report
     * files to write. Outs pair with formats by position, Behat style; "std"
     * (the default when a format has no -o) means the console. The first
     * console-bound format streams live; additional ones render at the end.
     *
     * @param Input $input the console input for the --format and -o options
     * @return array{console: string, files: array<int, array{string, string}>, extraConsole: array<int, string>}
     */
    private function resolveOutputs(Input $input): array
    {
        $formats = (array) $input->getOption('format');
        $outs = (array) $input->getOption('out');

        if ($formats === []) {
            $formats = [$this->config->getFormat()];
        }

        $console = null;
        $files = [];
        $extraConsole = [];

        foreach (array_values($formats) as $i => $format) {
            $file = $outs[$i] ?? 'std';

            if ($file !== 'std') {
                $files[] = [$format, $file];
            } elseif ($console === null) {
                $console = $format;
            } else {
                $extraConsole[] = $format;
            }
        }

        return [
            'console' => $console ?? 'pretty',
            'files' => $files,
            'extraConsole' => $extraConsole,
        ];
    }

    /**
     * Returns the requested formats that neither the built-in set nor a
     * loaded extension can provide.
     *
     * @param Input $input the console input for the --format option
     * @return array<int, string> the unknown format names
     */
    private function unknownFormats(Input $input): array
    {
        $known = ['pretty', 'dot', 'tap', 'junit', 'html'];

        return array_values(array_filter(
            (array) $input->getOption('format'),
            fn(string $format) => !in_array($format, $known, true)
                && !($this->extensionLoader?->hasFormatter($format) ?? false),
        ));
    }

    private function resolveFormat(Input $input): string
    {
        return $this->resolveOutputs($input)['console'];
    }

    private function createFormatter(string $format, Output $output): Formatter
    {
        if ($this->extensionLoader !== null && $this->extensionLoader->hasFormatter($format)) {
            return new FormatterBridge($this->extensionLoader->getFormatter($format), $output);
        }

        return match ($format) {
            'dot' => new Dot($output),
            'tap' => new Tap($output),
            'junit' => new Junit($output),
            'html' => new Html($output),
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
        if ($this->coveragePartials !== []) {
            $reporter->mergePartials($this->coveragePartials);
            $this->coveragePartials = [];
        }

        return $reporter->report($output, new CoverageOptions(
            srcPath: $input->getOption('coverage-src') ?? $this->config->getSrcPath(),
            showText: (bool) $input->getOption('coverage'),
            cloverPath: $input->getOption('coverage-clover'),
            htmlPath: $input->getOption('coverage-html'),
            jsonPath: $input->getOption('coverage-json'),
            coverageMin: $input->getOption('coverage-min'),
            partialPath: $input->getOption('coverage-partial'),
        ));
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
        $this->codeGenerator($interactive)->generate($output, $results, $fake);
    }

    /**
     * Builds a CodeGenerator configured from the project paths.
     *
     * @param bool $interactive whether generation should prompt (false = auto-accept)
     * @return CodeGenerator
     */
    private function codeGenerator(bool $interactive): CodeGenerator
    {
        return new CodeGenerator(
            ltrim($this->config->getSrcPath(), './'),
            ltrim($this->config->getSpecPath(), './'),
            $interactive,
            $this->config->getSpecSuffix(),
            $this->config->getPsr4Prefix(),
        );
    }
}
