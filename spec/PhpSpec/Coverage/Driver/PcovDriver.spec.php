<?php

use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Coverage\CoverageCollector;
use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\Driver\PcovDriver;
use PhpSpec\Coverage\LivePhpRuntime;

describe(PcovDriver::class, function () {

    it('is a coverage driver', function () {
        expect(new PcovDriver())->toBeAnInstanceOf(CoverageDriver::class);
    });

    // A cycle clears PCOV's counts, which would empty a live collection, so
    // these run only when none is in progress. PCOV traces src by default,
    // so the probe is a method of the source tree.
    if ((new LivePhpRuntime())->enabled(CoverageAdapter::Pcov) && CoverageRegistry::collector() === null) {

        $probe = (string) realpath(__DIR__ . '/../../../../src/PhpSpec/Coverage/CoverageCollector.php');

        it('reports the lines a cycle executed as 1 and the executable lines it did not reach as -1', function () use ($probe) {
            $driver = new PcovDriver();

            $driver->start();
            CoverageCollector::countLines([1 => 1, 2 => -2]);
            $data = $driver->stop();

            expect($data)->toHaveKey($probe);
            expect($data[$probe])->toContain(1);
            expect($data[$probe])->toContain(-1);
            $values = array_values(array_unique(array_merge(...array_values($data))));
            sort($values);
            expect($values)->toBe([-1, 1]);
        });

        it('starts each cycle from nothing', function () use ($probe) {
            $driver = new PcovDriver();

            $driver->start();
            CoverageCollector::countLines([1 => 1]);
            $driver->stop();
            $driver->start();
            $data = $driver->stop();

            expect(array_filter($data[$probe], fn (int $hit) => $hit > 0))->toBe([]);
        });

        it('keeps what it recorded across a pause, for the next stop', function () use ($probe) {
            $driver = new PcovDriver();

            $driver->start();
            CoverageCollector::countLines([1 => 1]);
            $driver->pause();
            $data = $driver->stop();

            expect($data[$probe])->toContain(1);
        });

        it('records nothing while paused', function () use ($probe) {
            $driver = new PcovDriver();

            $driver->start();
            $driver->pause();
            CoverageCollector::countLines([1 => 1]);
            $data = $driver->stop();

            expect(array_filter($data[$probe] ?? [], fn (int $hit) => $hit > 0))->toBe([]);
        });

    }

});
