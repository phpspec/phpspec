<?php

use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Coverage\Driver\PcovDriver;
use PhpSpec\Coverage\Driver\XdebugDriver;

describe(CoverageAdapter::class, function () {

    it('makes the driver of its engine', function () {
        expect(CoverageAdapter::Pcov->driver())->toBeAnInstanceOf(PcovDriver::class);
        expect(CoverageAdapter::Xdebug->driver())->toBeAnInstanceOf(XdebugDriver::class);
    });

    it('switches its engine on and the other off for a child process', function () {
        expect(CoverageAdapter::Pcov->ini())->toBe(['pcov.enabled=1', 'xdebug.mode=off']);
        expect(CoverageAdapter::Xdebug->ini())->toBe(['xdebug.mode=coverage', 'pcov.enabled=0']);
    });

    it('says what a run that wanted coverage needs when its engine cannot collect', function () {
        expect(CoverageAdapter::Pcov->refusal())->toBe('Code coverage requires PCOV enabled');
        expect(CoverageAdapter::Xdebug->refusal())->toBe('Code coverage requires Xdebug with coverage mode enabled');
    });

    it('names the same command under its engine as the remedy', function () {
        expect(CoverageAdapter::Pcov->remedy('vendor/bin/phpspec run --coverage'))->toBe('php -d pcov.enabled=1 vendor/bin/phpspec run --coverage');
        expect(CoverageAdapter::Xdebug->remedy('vendor/bin/phpspec run --coverage'))->toBe('XDEBUG_MODE=coverage vendor/bin/phpspec run --coverage');
    });

});
