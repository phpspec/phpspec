<?php

use PhpSpec\Coverage\CoverageData;

describe(CoverageData::class, function () {

    it('counts covered and executable lines', function () {
        $lines = [1 => 1, 2 => 1, 3 => 0, 4 => -2, 5 => 1];
        $result = CoverageData::countLines($lines);
        expect($result['covered'])->toBe(3);
        expect($result['executable'])->toBe(4);
    });

    it('returns zeros for empty data', function () {
        $result = CoverageData::countLines([]);
        expect($result['covered'])->toBe(0);
        expect($result['executable'])->toBe(0);
    });

    it('skips all non-executable lines', function () {
        $lines = [1 => -2, 2 => -2];
        $result = CoverageData::countLines($lines);
        expect($result['executable'])->toBe(0);
    });

    it('filterData leaves out eval\'d code', function () {
        $filtered = CoverageData::filterData([
            realpath('src') . '/PhpSpec/Mock/Double.php' => [1 => 1, 2 => 1],
            realpath('src') . "/PhpSpec/Mock/Double.php(575) : eval()'d code" => [1 => 1, 2 => 0, 3 => -2],
            realpath('src') . '/PhpSpec/Loader.php' => [1 => 1],
        ], 'src');

        expect(array_keys($filtered))->toBe(['PhpSpec/Loader.php', 'PhpSpec/Mock/Double.php']);
    });

    it('filterData keeps the files of several source directories, named from the project root, and passes over one that is not there', function () {
        $data = [
            realpath('src') . '/PhpSpec/Loader.php' => [1 => 1],
            realpath('spec') . '/PhpSpec/Loader.spec.php' => [1 => 1],
            realpath('features') . '/steps/runner.steps.php' => [1 => 1],
        ];

        expect(array_keys(CoverageData::filterData($data, 'src', 'spec')))->toBe(['spec/PhpSpec/Loader.spec.php', 'src/PhpSpec/Loader.php']);
        expect(array_keys(CoverageData::filterData($data, 'src', 'nowhere')))->toBe(['PhpSpec/Loader.php']);
    });

    it('filterData matches Windows-style backslash paths', function () {
        $realSrc = (string) realpath('src');
        $windowsStyleFile = str_replace('/', '\\', $realSrc . '/PhpSpec/Loader.php');

        $filtered = CoverageData::filterData([$windowsStyleFile => [1 => 1]], 'src');

        expect($filtered)->toHaveKey('PhpSpec/Loader.php');
    });

});
