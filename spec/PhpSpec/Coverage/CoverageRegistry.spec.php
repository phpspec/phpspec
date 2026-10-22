<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;

describe(CoverageRegistry::class, function () {

    // The run's own collector, when coverage of this suite is being taken,
    // is put back for the examples after these.
    beforeEach(function () {
        $this->collecting = CoverageRegistry::collector();
    });

    afterEach(function () {
        $this->collecting === null ? CoverageRegistry::reset() : CoverageRegistry::activate($this->collecting);
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
