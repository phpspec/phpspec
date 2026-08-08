<?php

use PhpSpec\Guard\Coverage;

describe(Coverage::class, function () {

    it('answers all three of covered, unreached and not executable', function () {
        $coverage = Coverage::fromHits(['/app/src/Basket.php' => [7 => 1, 8 => -1, 9 => -2]], '/app');

        expect($coverage->covers('src/Basket.php', 7))->toBeTrue();
        expect($coverage->covers('src/Basket.php', 8))->toBeFalse();
        expect($coverage->isExecutable('src/Basket.php', 8))->toBeTrue();
        expect($coverage->isExecutable('src/Basket.php', 9))->toBeFalse();
    });

    it('knows which files the run loaded', function () {
        $coverage = Coverage::fromHits(['/app/src/Basket.php' => [7 => 1]], '/app');

        expect($coverage->knows('src/Basket.php'))->toBeTrue();
        expect($coverage->knows('src/Coupon.php'))->toBeFalse();
    });

    // Guard reads a file the run never loaded from its source, and calls every
    // logic line untested. That is right for one file and wrong for all of
    // them: a collector that reported nothing is a broken collector, not a
    // codebase without a single example, and guard must be able to tell.
    // Xdebug reports a file as the filesystem resolved it, while the base comes
    // from wherever the run was started. On Windows those are two spellings of
    // one directory, and a base that recognises only its own leaves every file
    // unknown, which guard reads as logic no example reached.
    it('matches a file whose path spells the project differently', function () {
        $directory = sys_get_temp_dir() . '/phpspec_cov_' . getmypid();
        @mkdir($directory . '/src', 0777, true);

        try {
            $hits = [(string) realpath($directory) . '/src/Basket.php' => [7 => 1]];

            $coverage = Coverage::fromHits($hits, $directory . '/src/..');

            expect($coverage->knows('src/Basket.php'))->toBeTrue();
            expect($coverage->covers('src/Basket.php', 7))->toBeTrue();
        } finally {
            @rmdir($directory . '/src');
            @rmdir($directory);
        }
    });

    it('says when it holds no evidence at all', function () {
        expect(Coverage::nothing()->isEmpty())->toBeTrue();
        expect(Coverage::fromHits([], '/app')->isEmpty())->toBeTrue();
        expect(Coverage::fromHits(['/app/src/Basket.php' => [7 => 1]], '/app')->isEmpty())->toBeFalse();
    });

});
