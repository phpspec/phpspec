<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;

describe(CoverageRegistry::class, function () {

    afterEach(function () {
        CoverageRegistry::reset();
    });

    it('has no collector by default', function () {
        expect(CoverageRegistry::collector())->toBeNull();
    });

    it('exposes the collector once activated', function (CoverageDriver $driver) {
        $collector = new PerExampleCollector($driver);

        CoverageRegistry::activate($collector);

        expect(CoverageRegistry::collector())->toBe($collector);
    });

    it('clears the collector on reset', function (CoverageDriver $driver) {
        CoverageRegistry::activate(new PerExampleCollector($driver));

        CoverageRegistry::reset();

        expect(CoverageRegistry::collector())->toBeNull();
    });
});
