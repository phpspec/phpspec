<?php

use PhpSpec\Coverage\CoverageCollector;
use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\Driver\XdebugDriver;

describe(XdebugDriver::class, function () {

    it('is a coverage driver', function () {
        expect(new XdebugDriver())->toBeAnInstanceOf(CoverageDriver::class);
    });

    // Cycling xdebug start/stop would clobber a live whole-suite collection
    // (xdebug coverage state is process-global), so skip these when one is running.
    if (CoverageCollector::isAvailable() && !CoverageCollector::isCollecting()) {

        it('collects line hits between start and stop', function () {
            $driver = new XdebugDriver();

            $driver->start();
            $probe = require __DIR__ . '/fixtures/covered_probe.php';
            $data = $driver->stop();

            expect($probe)->toBe('covered');
            expect($data)->toHaveKey(realpath(__DIR__ . '/fixtures/covered_probe.php'));
        });

        it('starts a fresh cycle each time', function () {
            $driver = new XdebugDriver();

            $driver->start();
            $driver->stop();
            $driver->start();
            $data = $driver->stop();

            expect($data)->not()->toHaveKey(realpath(__DIR__ . '/fixtures/covered_probe.php'));
        });

    }

});
