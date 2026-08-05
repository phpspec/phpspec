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
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Coverage\CoverageOptions;
use PhpSpec\Coverage\CoverageVerdict;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Extensions\FormatterBridge;
use PhpSpec\FilterRegistry;
use PhpSpec\LineTargetRegistry;
use PhpSpec\Loader;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;
use PhpSpec\Parallel\ParallelRunner;
use PhpSpec\Report\Formatter;
use PhpSpec\Report\Formatter\Agent;
use PhpSpec\Report\Formatter\Agent\Offers;
use PhpSpec\Report\Formatter\Agent\ShutdownProcessEnd;
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
use Symfony\Component\Console\Output\BufferedOutput;
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
            ->addOption('accept-offers', null, Option::VALUE_NONE, 'Apply all pending code-generation offers non-interactively, then exit (for --format=agent consumers)')
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
        $format = $this->resolveFormat($input);
        $formatter = $this->createFormatter($format, $output);
        // The agent document IS the console under --format=agent, so every human
        // line the command would otherwise print (the seed, the profile table, the
        // coverage verdict, an error) is routed off the console: what an agent
        // needs is told to the formatter instead and rides inside the document.
        // For every other format the two are the same stream.
        $document = $formatter instanceof Agent ? $formatter : null;
        $prose = $document !== null ? new BufferedOutput() : $output;

        // Resolved once, here: it is what the run targets, and it recovers the
        // positional path a "--parallel features" swallows, so asking twice
        // would answer differently the second time.
        $files = $this->suitePaths($input);
        $document?->targets($files);

        // PHP prints a fatal to standard output as well as the error stream, and
        // standard output is the document's. Sending its own reports to the error
        // stream leaves the channel clean; the document names the fatal itself.
        $displayErrors = $document !== null ? (string) ini_set('display_errors', 'stderr') : null;

        try {
            return $this->perform($input, $prose, $formatter, $files);
        } finally {
            $document?->publish();
            if ($displayErrors !== null) {
                ini_set('display_errors', $displayErrors);
            }
        }
    }

    /**
     * The run itself, writing every human line to the prose channel and results
     * through the formatter.
     *
     * @param Input $input the console input (arguments and options)
     * @param Output $prose where human-facing lines go
     * @param Formatter $formatter the console formatter for the run's results
     * @param string $files the paths this run targets, as the loader takes them
     * @return int exit code: 0 = success, 1 = failure/error or bootstrap missing, 2 = coverage below minimum
     *
     * @throws RandomException
     * @throws DOMException
     */
    private function perform(Input $input, Output $prose, Formatter $formatter, string $files): int
    {
        $missingBootstrap = $this->loadBootstrap($input);

        if ($missingBootstrap !== null) {
            return $this->stopped($prose, $formatter, $missingBootstrap);
        }
        $this->registerAutoloader();

        $pathsFrom = $input->getOption('paths-from');

        if ($pathsFrom !== null && !is_file($pathsFrom)) {
            return $this->stopped($prose, $formatter, "Paths file not found: $pathsFrom");
        }

        $unknownFormats = $this->unknownFormats($input);

        if ($unknownFormats !== []) {
            return $this->stopped($prose, $formatter, 'Unknown format: ' . implode(', ', $unknownFormats) . ' (available: pretty, dot, tap, junit, html, agent)');
        }

        $coverageReporter = $this->startCoverage($input);

        if (is_string($coverageReporter)) {
            return $this->stopped($prose, $formatter, $coverageReporter);
        }

        try {
            $results = $this->runSuiteStreaming($input, $prose, $formatter, $files);
        } catch (\RuntimeException $e) {
            // A load-time contract violation (e.g. two step definitions
            // sharing a title) is the user's to fix; report it, never a trace.
            return $this->stopped($prose, $formatter, $e->getMessage());
        }

        $this->writeReportFiles($input, $prose, $results);
        $this->printProfile($input, $prose, $results);

        if ($coverageReporter) {
            $verdict = $this->reportCoverage($input, $prose, $coverageReporter);

            if ($verdict !== null) {
                // The gate's verdict belongs in the document as much as in the
                // exit code: an agent that reads one and not the other would
                // otherwise call a failed run green.
                if ($formatter instanceof Agent) {
                    $formatter->covered($verdict);
                }

                if ($verdict->exitCode() !== null) {
                    return $verdict->exitCode();
                }
            }
        }

        if ($input->getOption('accept-offers')) {
            // Apply every pending generation offer without prompting, then exit.
            // The run's own output already went out; generation notes are discarded
            // so an upstream --format=agent document stays a single clean object.
            $this->generateCode(new BufferedOutput(), $results, (bool) $input->getOption('fake'), false);

            return 0;
        }

        // When pair mode spawned this run (it sets an env var naming a report
        // file), record the generation candidates there and let pair mode drive
        // the interactive generation in the REPL. Otherwise generate here as usual.
        $reportPath = GenerationReport::requestedPath();
        if ($reportPath !== null) {
            GenerationReport::write($reportPath, new RunOutcome(
                $this->codeGenerator(false)->scan($results),
                SuiteSummary::fromSuiteResult($results),
            ));
        } elseif (!in_array($this->resolveFormat($input), ['junit', 'html', 'agent'], true)) {
            $this->generateCode($prose, $results, (bool) $input->getOption('fake'), $input->isInteractive());
        }

        if ($formatter instanceof Agent) {
            // A reader that was told about an offer can take it later by id,
            // so what was reported has to still be there when they do.
            $this->recordOffers($results);
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
     * Reports what stopped the run before it could produce results: red on the
     * console for a human, and inside the document for an agent, so a run that
     * returns nothing never leaves either of them guessing why.
     *
     * @param Output $prose the channel for the run's human-facing lines
     * @param Formatter $formatter the console formatter for the run's results
     * @param string $message what went wrong
     * @return int the exit code the caller returns
     */
    private function stopped(Output $prose, Formatter $formatter, string $message): int
    {
        $prose->writeln(sprintf('<fg=red>%s</>', $message));

        if ($formatter instanceof Agent) {
            $formatter->stopped($message);
        }

        return 1;
    }

    /**
     * Resolves and requires the bootstrap file from --bootstrap, config, or vendor/autoload.php.
     *
     * @param Input $input the console input to read --bootstrap from
     * @return string|null null once loaded (or when none is needed), or why it could not be
     */
    private function loadBootstrap(Input $input): ?string
    {
        $bootstrap = $input->getOption('bootstrap') ?? $this->config->getBootstrap();
        if ($bootstrap === null && file_exists('vendor/autoload.php')) {
            $bootstrap = 'vendor/autoload.php';
        }
        if ($bootstrap === null) {
            return null;
        }
        if (!file_exists($bootstrap)) {
            return "Bootstrap file not found: $bootstrap";
        }
        require $bootstrap;

        return null;
    }

    private function registerAutoloader(): void
    {
        $this->config->registerAutoloaders();
    }

    /**
     * Starts code coverage collection if any --coverage* option was given.
     *
     * @param Input $input the console input to check coverage options
     * @return CoverageReporter|null|string the reporter once started, null when no
     *                                      coverage was asked for, or why it could not start
     */
    private function startCoverage(Input $input): CoverageReporter|null|string
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

        return $reporter->start(perExample: $perExample) ?? $reporter;
    }

    /**
     * What this run targets, as the loader will be given it: the paths named on
     * the command line (and any read from --paths-from), else the suite the
     * flags ask for, else everything the config declares. Resolved before the
     * suite is loaded, so a run that dies while loading can still say what it
     * was trying to run.
     *
     * @param Input $input the console input for the file arguments and suite flags
     * @return string the comma-separated paths
     */
    private function suitePaths(Input $input): string
    {
        $paths = $input->getArgument('files');

        // VALUE_OPTIONAL may swallow the positional arg: --parallel features → parallel="features"
        $parallel = $input->getOption('parallel');
        if ($parallel !== false && is_string($parallel) && !ctype_digit($parallel)) {
            $paths[] = $parallel;
            $input->setOption('parallel', null);
        }

        $pathsFrom = $input->getOption('paths-from');

        if ($pathsFrom !== null && is_file($pathsFrom)) {
            $listed = file($pathsFrom, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $paths = array_merge($paths, array_filter(array_map('trim', $listed ?: [])));
        }

        if (!empty($paths)) {
            return implode(',', $paths);
        }

        if ($input->getOption('story')) {
            return $this->config->getFeaturesPath();
        }

        if ($input->getOption('all')) {
            $suitePaths = $this->config->getAllLoadPaths();

            return str_contains($suitePaths, 'features') ? $suitePaths : $suitePaths . ',features/';
        }

        return $this->config->getAllLoadPaths();
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
     * @param Output $prose the channel for the run's human-facing lines
     * @param Formatter $formatter the console formatter the results stream through
     * @param string $files the paths this run targets, as the loader takes them
     * @return SuiteResult the aggregated suite results
     *
     * @throws RandomException
     * @throws \RuntimeException when the loader rejects a duplicate step title
     */
    private function runSuiteStreaming(Input $input, Output $prose, Formatter $formatter, string $files): SuiteResult
    {
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
            $prose->writeln("<fg=yellow>Randomised with seed $seed</>");

            if ($formatter instanceof Agent) {
                $formatter->randomisedWith($seed);
            }
        }

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
        $known = ['pretty', 'dot', 'tap', 'junit', 'html', 'agent'];

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
            'agent' => new Agent(
                $output,
                fn(SuiteResult $results) => $this->codeGenerator(false)->scan($results)->toArray(),
                new ShutdownProcessEnd(),
            ),
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
     * @return CoverageVerdict|null what the run covered, or null when nothing was collected
     * @throws DOMException
     */
    private function reportCoverage(Input $input, Output $output, CoverageReporter $reporter): ?CoverageVerdict
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
    /**
     * Puts this run's generation offers on the table under the same ids the
     * document reports, so `phpspec accept <id>` generates exactly the one that
     * was read. Both sides derive the id from the offer itself, so neither has
     * to tell the other what it chose.
     */
    private function recordOffers(SuiteResult $results): void
    {
        $candidates = $this->codeGenerator(false)->scan($results)->toArray();
        $offers = [];

        foreach (Offers::fromCandidates($candidates) as $offer) {
            $offers[] = Offer::generate(
                $offer['action'],
                $offer['target'],
                Offers::candidateFor($candidates, $offer['action'], $offer['target']),
            );
        }

        if ($offers !== []) {
            (new OfferBook())->record(...$offers);
        }
    }

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
