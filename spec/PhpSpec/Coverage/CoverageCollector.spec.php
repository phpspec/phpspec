<?php

use PhpSpec\Coverage\CoverageCollector;

describe(CoverageCollector::class, function () {

    it('counts covered and executable lines', function () {
        $lines = [1 => 1, 2 => 1, 3 => 0, 4 => -2, 5 => 1];
        $result = CoverageCollector::countLines($lines);
        expect($result['covered'])->toBe(3);
        expect($result['executable'])->toBe(4);
    });

    it('returns zeros for empty data', function () {
        $result = CoverageCollector::countLines([]);
        expect($result['covered'])->toBe(0);
        expect($result['executable'])->toBe(0);
    });

    it('skips all non-executable lines', function () {
        $lines = [1 => -2, 2 => -2];
        $result = CoverageCollector::countLines($lines);
        expect($result['executable'])->toBe(0);
    });

    if (CoverageCollector::isAvailable() && !CoverageCollector::isCollecting()) {

        it('tracks whether a whole-suite collection is in progress', function () {
            $collector = new CoverageCollector();
            expect(CoverageCollector::isCollecting())->toBeFalse();

            $collector->start();
            expect(CoverageCollector::isCollecting())->toBeTrue();

            $collector->stop();
            expect(CoverageCollector::isCollecting())->toBeFalse();
        });

    }

    it('filter excludes eval\'d code paths', function () {
        $collector = new CoverageCollector();

        // Simulate xdebug data with an eval'd code path
        $ref = new \ReflectionProperty($collector, 'data');
        $ref->setValue($collector, [
            realpath('src') . '/PhpSpec/Mock/Double.php' => [1 => 1, 2 => 1],
            realpath('src') . "/PhpSpec/Mock/Double.php(575) : eval()'d code" => [1 => 1, 2 => 0, 3 => -2],
            realpath('src') . '/PhpSpec/Loader.php' => [1 => 1],
        ]);

        $filtered = $collector->filter('src');

        expect($filtered)->toHaveKey('PhpSpec/Mock/Double.php');
        expect($filtered)->toHaveKey('PhpSpec/Loader.php');
        expect(array_keys($filtered))->not()->toContain("PhpSpec/Mock/Double.php(575) : eval()'d code");
    });

});
