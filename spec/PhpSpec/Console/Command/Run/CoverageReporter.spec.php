<?php

use PhpSpec\Console\Command\Run\CoverageReporter;
use PhpSpec\Coverage\CoverageCollector;
use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageOptions;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;
use Symfony\Component\Console\Output\BufferedOutput;

describe(CoverageReporter::class, function () {

    context('start', function () {

        if (CoverageCollector::isAvailable()) {

            // Starting/stopping a real collection would clobber a live
            // whole-suite collection (xdebug coverage state is process-global).
            if (!CoverageCollector::isCollecting()) {

                it('starts with nothing to report when Xdebug coverage is available', function () {
                    $output = new BufferedOutput();
                    $reporter = new CoverageReporter();

                    expect($reporter->start())->toBeNull();

                    // Stop the collection so no state leaks into later examples
                    $reporter->report($output, new CoverageOptions(srcPath: 'src'));
                    expect(CoverageCollector::isCollecting())->toBeFalse();
                });

            }

            it('activates the per-example coverage registry in per-example mode', function () {
                $output = new BufferedOutput();
                $reporter = new CoverageReporter();

                try {
                    expect($reporter->start(perExample: true))->toBeNull();
                    expect(CoverageRegistry::collector())->toBeAnInstanceOf(PerExampleCollector::class);
                } finally {
                    CoverageRegistry::reset();
                }
            });

        } else {

            it('returns the reason when Xdebug coverage is not available', function () {
                $reporter = new CoverageReporter();
                expect($reporter->start())->toContain('Code coverage requires Xdebug');
            });

            it('returns the reason in per-example mode when Xdebug coverage is not available', function () {
                $reporter = new CoverageReporter();
                expect($reporter->start(perExample: true))->toContain('Code coverage requires Xdebug');
            });

        }

    });

    context('report', function () {

        it('reports nothing when collection was never started', function () {
            $output = new BufferedOutput();
            $reporter = new CoverageReporter();
            $options = new CoverageOptions(srcPath: 'src');

            expect($reporter->report($output, $options))->toBeNull();
            expect($output->fetch())->toBe('');
        });

        it('renders the requested reports from a per-example collection', function (CoverageDriver $driver) {
            $fixture = (string) realpath(__DIR__ . '/../../../Coverage/Driver/fixtures/covered_probe.php');
            allow($driver->stop())->toReturn([$fixture => [3 => 1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/PhpSpec/Coverage/JsonReport.spec.php');
            $collector->beginExample();
            $collector->endExample('probes');
            CoverageRegistry::activate($collector);

            $dir = sys_get_temp_dir() . '/phpspec_reports_' . uniqid();
            $output = new BufferedOutput();
            $reporter = new CoverageReporter();

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
            allow($driver->stop())->toReturn([$fixture => [3 => 1, 4 => -1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/PhpSpec/Coverage/JsonReport.spec.php');
            $collector->beginExample();
            $collector->endExample('probes');
            CoverageRegistry::activate($collector);

            $output = new BufferedOutput();
            $reporter = new CoverageReporter();

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
            allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/App/Calculator.spec.php');
            $collector->beginExample();
            $collector->endExample('adds');
            CoverageRegistry::activate($collector);

            $partialPath = sys_get_temp_dir() . '/phpspec_partial_' . uniqid() . '.json';
            $output = new BufferedOutput();
            $reporter = new CoverageReporter();

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
            allow($workerDriver->stop())->toReturn(['/project/src/App/Greeter.php' => [8 => 1]]);
            $workerCollector = new PerExampleCollector($workerDriver);
            $workerCollector->beginSpec('spec/App/Greeter.spec.php');
            $workerCollector->beginExample();
            $workerCollector->endExample('greets');

            $partialPath = sys_get_temp_dir() . '/phpspec_partial_' . uniqid() . '.json';
            file_put_contents($partialPath, json_encode($workerCollector->toArray()));

            CoverageRegistry::activate(new PerExampleCollector($driver));
            $reporter = new CoverageReporter();

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
