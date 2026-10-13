<?php

use PhpSpec\Coverage\CoverageCollector;
use PhpSpec\Coverage\CoverageDriver;

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

    // Starting and stopping flips the flag a live whole-suite collection
    // relies on, so these run only when none is in progress.
    if (!CoverageCollector::isCollecting()) {

        it('tracks whether a whole-suite collection is in progress', function (CoverageDriver $driver) {
            allow($driver->start());
            allow($driver->stop())->toReturn([]);
            $collector = new CoverageCollector($driver);
            expect(CoverageCollector::isCollecting())->toBeFalse();

            $collector->start();
            expect(CoverageCollector::isCollecting())->toBeTrue();

            $collector->stop();
            expect(CoverageCollector::isCollecting())->toBeFalse();
        });

        it('keeps what the driver collected between start and stop', function (CoverageDriver $driver) {
            allow($driver->start());
            allow($driver->stop())->toReturn(['/app/src/A.php' => [1 => 1, 2 => -1]]);
            $collector = new CoverageCollector($driver);

            $collector->start();
            $collector->stop();

            expect($collector->getData())->toBe(['/app/src/A.php' => [1 => 1, 2 => -1]]);
        });

        it('filter excludes eval\'d code paths', function (CoverageDriver $driver) {
            allow($driver->start());
            allow($driver->stop())->toReturn([
                realpath('src') . '/PhpSpec/Mock/Double.php' => [1 => 1, 2 => 1],
                realpath('src') . "/PhpSpec/Mock/Double.php(575) : eval()'d code" => [1 => 1, 2 => 0, 3 => -2],
                realpath('src') . '/PhpSpec/Loader.php' => [1 => 1],
            ]);
            $collector = new CoverageCollector($driver);
            $collector->start();
            $collector->stop();

            $filtered = $collector->filter('src');

            expect($filtered)->toHaveKey('PhpSpec/Mock/Double.php');
            expect($filtered)->toHaveKey('PhpSpec/Loader.php');
            expect(array_keys($filtered))->not()->toContain("PhpSpec/Mock/Double.php(575) : eval()'d code");
        });

    }

    it('filterData matches Windows-style backslash paths', function () {
        $realSrc = (string) realpath('src');
        $windowsStyleFile = str_replace('/', '\\', $realSrc . '/PhpSpec/Loader.php');

        $filtered = CoverageCollector::filterData([$windowsStyleFile => [1 => 1]], 'src');

        expect($filtered)->toHaveKey('PhpSpec/Loader.php');
    });

});
