<?php

use PhpSpec\Console\Command\Run\CoverageReporter;
use PhpSpec\Coverage\CoverageAdapter;
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

    // The run's own collector, when coverage of this suite is being taken,
    // is out of the way of these examples and put back after each.
    beforeEach(function () {
        $this->collecting = CoverageRegistry::collector();
        CoverageRegistry::reset();
    });

    afterEach(function () {
        $this->collecting === null ? CoverageRegistry::reset() : CoverageRegistry::activate($this->collecting);
    });

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

        // Collecting asks the engine itself, so these run under the engine
        // this process has, and never beside a live collection.
        $live = new LivePhpRuntime();

        if ((new CoverageEngines(null, $live, ''))->inThisProcess(['src']) instanceof CoverageAdapter && CoverageRegistry::collector() === null) {

            it('collects inside each example with the engine this process has, crediting none', function () use ($live) {
                $reporter = new CoverageReporter(new CoverageEngines(null, $live, ''));

                expect($reporter->start(['src']))->toBeNull();
                $collector = CoverageRegistry::collector();
                $collector->beginSpec('spec/Probe.spec.php');
                $collector->beginExample();
                $collector->endExample('probes');
                $reporter->report(new BufferedOutput(), new CoverageOptions(sources: ['src']));

                expect($reporter->adapter())->toBeAnInstanceOf(CoverageAdapter::class);
                expect($reporter->hits())->not()->toBeEmpty();
                expect($collector->getTests())->toBe([]);
                expect(CoverageRegistry::collector())->toBeNull();
            });

            it('credits each example when asked to collect per example', function () use ($live) {
                $reporter = new CoverageReporter(new CoverageEngines(null, $live, ''));

                $reporter->start(['src'], perExample: true);
                $collector = CoverageRegistry::collector();
                $collector->beginSpec('spec/Probe.spec.php');
                $collector->beginExample();
                $collector->endExample('probes');
                $reporter->report(new BufferedOutput(), new CoverageOptions(sources: ['src']));

                expect($collector->getTests())->toHaveKey('spec/Probe.spec.php::probes');
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
            $options = new CoverageOptions(sources: ['src']);

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

                return (string) file_get_contents($path);
            });
            $output = new BufferedOutput();
            $reporter = new CoverageReporter(new CoverageEngines(null, $php, ''), new Exclusions(['Excluded.php'], $fs, dirname($fixture)));

            $verdict = $reporter->report($output, new CoverageOptions(sources: [dirname($fixture)], showText: true));

            expect($verdict->percentage->value())->toBe(100.0);
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
                    sources: [dirname($fixture)],
                    showText: true,
                    cloverPath: $dir . '/clover.xml',
                    htmlPath: $dir . '/html',
                    jsonPath: $dir . '/coverage.json',
                    coverageMin: '100',
                ));

                expect($verdict->met())->toBeTrue();
                expect($verdict->exitCode())->toBeNull();
                expect($verdict->percentage->value())->toBe(100.0);
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
            allow($driver->stop())->toReturn([$fixture => [3 => -1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/PhpSpec/Coverage/JsonReport.spec.php');
            $collector->beginExample();
            $collector->endExample('probes');
            CoverageRegistry::activate($collector);

            $output = new BufferedOutput();
            $reporter = $this->reporter;

            try {
                $verdict = $reporter->report($output, new CoverageOptions(
                    sources: [dirname($fixture)],
                    coverageMin: '90',
                ));

                expect($verdict->met())->toBeFalse();
                expect($verdict->exitCode())->toBe(1);
                expect($verdict->percentage->value())->toBe(0.0);
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
                $exitCode = $reporter->report($output, new CoverageOptions(sources: ['src'], partialPath: $partialPath));

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

        it('refuses to report when part of the coverage never arrived, saying why', function (CoverageDriver $driver) {
            allow($driver->start());
            allow($driver->stop())->toReturn([]);
            CoverageRegistry::activate(new PerExampleCollector($driver));
            $unreadable = sys_get_temp_dir() . '/phpspec_partial_' . uniqid() . '.json';
            file_put_contents($unreadable, '{"tests": ');
            $output = new BufferedOutput();
            $reporter = $this->reporter;

            $reporter->mergePartials([$unreadable], ['Coverage is incomplete: a worker exited with code 255 before handing over its coverage.']);
            $refusal = $reporter->report($output, new CoverageOptions(sources: ['src'], showText: true, coverageMin: '90'));

            expect($refusal)->toBeAnInstanceOf(CoverageRefusal::class);
            expect($refusal->reason)->toBe("Coverage is incomplete: a worker exited with code 255 before handing over its coverage.\nCoverage is incomplete: a worker handed over coverage that could not be read.");
            expect($output->fetch())->toBe('');
            expect(file_exists($unreadable))->toBeFalse();
            expect(CoverageRegistry::collector())->toBeNull();
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
