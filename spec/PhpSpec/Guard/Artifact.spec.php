<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Artifact;
use PhpSpec\Guard\Coverage;

describe(Artifact::class, function () {

    it('says so when the report is not there', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect((new Artifact($fs))->read('cov.json'))->toBe('Coverage file not found: cov.json.');
    });

    it('says so when the file is not a coverage report', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('{"hello":"world"}');

        expect((new Artifact($fs))->read('cov.json'))
            ->toBe('cov.json is not a PhpSpec coverage report: run --coverage-json to make one.');
    });

    // "lines" holds what ran and "executable" what there was to run. Without
    // the second, a line nothing reached and a line that was never code look
    // the same, and guard would call a closing brace untested logic.
    it('reads what ran and what there was to run', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(json_encode([
            'sources' => [
                'src/Basket.php' => [
                    'checksum' => 'abc',
                    'lines' => [9 => ['Basket::totals']],
                    'executable' => [9, 14, 15],
                ],
            ],
        ]));

        $coverage = (new Artifact($fs))->read('cov.json');

        expect($coverage)->toBeAnInstanceOf(Coverage::class);
        expect($coverage->covers('src/Basket.php', 9))->toBeTrue();
        expect($coverage->covers('src/Basket.php', 14))->toBeFalse();
        expect($coverage->isExecutable('src/Basket.php', 14))->toBeTrue();
        // Never code, so guard has nothing to say about it.
        expect($coverage->isExecutable('src/Basket.php', 13))->toBeFalse();
    });

    // Reports made before guard existed have no "executable" list; what ran is
    // the most that can be known from them.
    it('falls back to what ran when the report predates the executable list', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(json_encode([
            'sources' => ['src/Basket.php' => ['checksum' => 'abc', 'lines' => [9 => ['Basket::totals']]]],
        ]));

        $coverage = (new Artifact($fs))->read('cov.json');

        expect($coverage->covers('src/Basket.php', 9))->toBeTrue();
        expect($coverage->knows('src/Basket.php'))->toBeTrue();
    });

});
