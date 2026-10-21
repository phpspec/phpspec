<?php

use PhpSpec\Console\Command\Run\CoverageReporter;
use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Coverage\CoverageCollector;
use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageEngines;
use PhpSpec\Coverage\CoverageOptions;
use PhpSpec\Coverage\CoverageRefusal;
use PhpSpec\Coverage\Exclusions;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\LivePhpRuntime;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\Coverage\PhpRuntime;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;

describe(CoverageReporter::class, function () {

    context('start', function () {

        $runtime = function (PhpRuntime $php, array $enabled): void {
            allow($php->loaded(any()))->toReturnUsing(fn(CoverageAdapter $adapter) => in_array($adapter, $enabled, true));
            allow($php->enabled(any()))->toReturnUsing(fn(CoverageAdapter $adapter) => in_array($adapter, $enabled, true));
            allow($php->traces(any()))->toReturn(true);
        };

        it('passes on the refusal when no engine can collect, and names no engine', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, []);
            $reporter = new CoverageReporter(new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'));

            $refusal = $reporter->start(['src']);

            expect($refusal)->toBeAnInstanceOf(CoverageRefusal::class);
            expect($refusal->reason)->toBe('Code coverage requires PCOV or Xdebug in coverage mode');
            expect($reporter->adapter())->toBeNull();
        });

        it("activates the per-example registry with the engine's driver, asking the engine nothing yet", function (PhpRuntime $php) use ($runtime) {
            $runtime($php, [CoverageAdapter::Pcov]);
            $reporter = new CoverageReporter(new CoverageEngines(null, $php, ''));

            try {
                expect($reporter->start(['src'], perExample: true))->toBeNull();
                expect(CoverageRegistry::collector())->toBeAnInstanceOf(PerExampleCollector::class);
                expect($reporter->adapter())->toBe(CoverageAdapter::Pcov);
            } finally {
                CoverageRegistry::reset();
            }
        });

        // A whole-suite collection asks the engine itself, so this runs under
        // the engine this process has, and never beside a live collection.
        $live = new LivePhpRuntime();

        if ((new CoverageEngines(null, $live, ''))->inThisProcess(['src']) instanceof CoverageAdapter && !CoverageCollector::isCollecting()) {

            it('starts a whole-suite collection with the engine this process has', function () use ($live) {
                $output = new BufferedOutput();
                $reporter = new CoverageReporter(new CoverageEngines(null, $live, ''));

                expect($reporter->start(['src']))->toBeNull();
                expect($reporter->adapter())->toBeAnInstanceOf(CoverageAdapter::class);
                expect(CoverageCollector::isCollecting())->toBeTrue();

                $reporter->report($output, new CoverageOptions(srcPath: 'src'));
                expect(CoverageCollector::isCollecting())->toBeFalse();
            });

        }

    });

    context('report', function () {

        beforeEach(function (PhpRuntime $php) {
            $this->reporter = new CoverageReporter(new CoverageEngines(null, $php, ''));
        });

        it('reports nothing when collection was never started', function () {
            $output = new BufferedOutput();
            $reporter = $this->reporter;
            $options = new CoverageOptions(srcPath: 'src');

            expect($reporter->report($output, $options))->toBeNull();
            expect($output->fetch())->toBe('');
        });

        it('reports without what the exclusions leave out, and keeps the hits whole for guard', function (CoverageDriver $driver, PhpRuntime $php, Filesystem $fs) {
            $fixture = (string) realpath(__DIR__ . '/../../../Coverage/Driver/fixtures/covered_probe.php');
            $excluded = dirname($fixture) . '/Excluded.php';
            allow($driver->start());
            allow($driver->stop())->toReturn([$fixture => [3 => 1], $excluded => [4 => -1], '/elsewhere/vendor/Lib.php' => [9 => 1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/PhpSpec/Coverage/JsonReport.spec.php');
            $collector->beginExample();
            $collector->endExample('probes');
            CoverageRegistry::activate($collector);
            $read = [];
            allow($fs->exists(any()))->toReturn(true);
            allow($fs->read(any()))->toReturnUsing(function (string $path) use (&$read) {
                $read[] = $path;

                return "<?php\n";
            });
            $output = new BufferedOutput();
            $reporter = new CoverageReporter(new CoverageEngines(null, $php, ''), new Exclusions(['Excluded.php'], $fs, dirname($fixture)));

            $verdict = $reporter->report($output, new CoverageOptions(srcPath: dirname($fixture), showText: true));

            expect($verdict->percent)->toBe(100.0);
            expect($output->fetch())->not()->toContain('Excluded.php');
            expect($reporter->hits())->toHaveKey($excluded);
            expect($read)->toBe([dirname($fixture) . '/covered_probe.php']);
        });

        it('renders the requested reports from a per-example collection', function (CoverageDriver $driver) {
            $fixture = (string) realpath(__DIR__ . '/../../../Coverage/Driver/fixtures/covered_probe.php');
            allow($driver->start());
            allow($driver->stop())->toReturn([$fixture => [3 => 1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/PhpSpec/Coverage/JsonReport.spec.php');
            $collector->beginExample();
            $collector->endExample('probes');
            CoverageRegistry::activate($collector);

            $dir = sys_get_temp_dir() . '/phpspec_reports_' . uniqid();
            $output = new BufferedOutput();
            $reporter = $this->reporter;

            try {
                $verdict = $reporter->report($output, new CoverageOptions(
                    srcPath: dirname($fixture),
                    showText: true,
                    cloverPath: $dir . '/clover.xml',
                    htmlPath: $dir . '/html',
                    jsonPath: $dir . '/coverage.json',
                    coverageMin: '100',
                ));

                expect($verdict->met())->toBeTrue();
                expect($verdict->exitCode())->toBeNull();
                expect($verdict->percent)->toBe(100.0);
                expect($verdict->required)->toBe(100.0);
                expect(file_exists($dir . '/clover.xml'))->toBeTrue();
                expect(file_exists($dir . '/html/index.html'))->toBeTrue();
                expect(file_exists($dir . '/coverage.json'))->toBeTrue();
                expect($output->fetch())->toContain('meets the required');
                expect((string) file_get_contents($dir . '/coverage.json'))
                    ->toContain('spec/PhpSpec/Coverage/JsonReport.spec.php::probes');
            } finally {
                CoverageRegistry::reset();
                @unlink($dir . '/clover.xml');
                @unlink($dir . '/coverage.json');
                array_map('unlink', glob($dir . '/html/*') ?: []);
                @rmdir($dir . '/html');
                @rmdir($dir);
            }
        });

        it('returns a verdict that demands exit code 1 when coverage is below the minimum', function (CoverageDriver $driver) {
            $fixture = (string) realpath(__DIR__ . '/../../../Coverage/Driver/fixtures/covered_probe.php');
            allow($driver->start());
            allow($driver->stop())->toReturn([$fixture => [3 => 1, 4 => -1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/PhpSpec/Coverage/JsonReport.spec.php');
            $collector->beginExample();
            $collector->endExample('probes');
            CoverageRegistry::activate($collector);

            $output = new BufferedOutput();
            $reporter = $this->reporter;

            try {
                $verdict = $reporter->report($output, new CoverageOptions(
                    srcPath: dirname($fixture),
                    coverageMin: '90',
                ));

                expect($verdict->met())->toBeFalse();
                expect($verdict->exitCode())->toBe(1);
                expect($verdict->percent)->toBe(50.0);
                expect($output->fetch())->toContain('below the required');
            } finally {
                CoverageRegistry::reset();
            }
        });

        it('writes the raw collector state to the partial path instead of rendering reports', function (CoverageDriver $driver) {
            allow($driver->start());
            allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/App/Calculator.spec.php');
            $collector->beginExample();
            $collector->endExample('adds');
            CoverageRegistry::activate($collector);

            $partialPath = sys_get_temp_dir() . '/phpspec_partial_' . uniqid() . '.json';
            $output = new BufferedOutput();
            $reporter = $this->reporter;

            try {
                $exitCode = $reporter->report($output, new CoverageOptions(srcPath: 'src', partialPath: $partialPath));

                expect($exitCode)->toBeNull();
                $state = json_decode((string) file_get_contents($partialPath), true);
                expect($state['tests'])->toHaveKey('spec/App/Calculator.spec.php::adds');
                expect($state['aggregate'])->toHaveKey('/project/src/App/Calculator.php');
                expect(CoverageRegistry::collector())->toBeNull();
            } finally {
                CoverageRegistry::reset();
                if (file_exists($partialPath)) {
                    unlink($partialPath);
                }
            }
        });

        it('merges partial state files into the active collector and removes them', function (CoverageDriver $driver, CoverageDriver $workerDriver) {
            allow($workerDriver->start());
            allow($workerDriver->stop())->toReturn(['/project/src/App/Greeter.php' => [8 => 1]]);
            $workerCollector = new PerExampleCollector($workerDriver);
            $workerCollector->beginSpec('spec/App/Greeter.spec.php');
            $workerCollector->beginExample();
            $workerCollector->endExample('greets');

            $partialPath = sys_get_temp_dir() . '/phpspec_partial_' . uniqid() . '.json';
            file_put_contents($partialPath, json_encode($workerCollector->toArray()));

            CoverageRegistry::activate(new PerExampleCollector($driver));
            $reporter = $this->reporter;

            try {
                $reporter->mergePartials([$partialPath]);

                expect(CoverageRegistry::collector()->getTests())->toHaveKey('spec/App/Greeter.spec.php::greets');
                expect(file_exists($partialPath))->toBeFalse();
            } finally {
                CoverageRegistry::reset();
                if (file_exists($partialPath)) {
                    unlink($partialPath);
                }
            }
        });

    });

});
