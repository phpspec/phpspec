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

    it('filterData leaves out eval\'d code', function () {
        $filtered = CoverageCollector::filterData([
            realpath('src') . '/PhpSpec/Mock/Double.php' => [1 => 1, 2 => 1],
            realpath('src') . "/PhpSpec/Mock/Double.php(575) : eval()'d code" => [1 => 1, 2 => 0, 3 => -2],
            realpath('src') . '/PhpSpec/Loader.php' => [1 => 1],
        ], 'src');

        expect(array_keys($filtered))->toBe(['PhpSpec/Loader.php', 'PhpSpec/Mock/Double.php']);
    });

    it('filterData matches Windows-style backslash paths', function () {
        $realSrc = (string) realpath('src');
        $windowsStyleFile = str_replace('/', '\\', $realSrc . '/PhpSpec/Loader.php');

        $filtered = CoverageCollector::filterData([$windowsStyleFile => [1 => 1]], 'src');

        expect($filtered)->toHaveKey('PhpSpec/Loader.php');
    });

});
