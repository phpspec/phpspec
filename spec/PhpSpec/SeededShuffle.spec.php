<?php

use PhpSpec\SeededShuffle;

describe(SeededShuffle::class, function () {
    it('orders the same items the same way for the same seed, as a random run orders its files', function () {
        expect((new SeededShuffle(42))->of(['A', 'B', 'C', 'D', 'E']))->toBe(['E', 'A', 'B', 'D', 'C']);
        expect((new SeededShuffle(42))->of(['A', 'B', 'C', 'D', 'E']))->toBe(['E', 'A', 'B', 'D', 'C']);
        expect((new SeededShuffle(7))->of(['A', 'B', 'C', 'D', 'E']))->toBe(['C', 'D', 'B', 'E', 'A']);
    });

    it('keeps no keys', function () {
        expect((new SeededShuffle(42))->of(['spec/A.spec.php' => 1, 'spec/B.spec.php' => 2]))->toHaveLength(2);
        expect(array_is_list((new SeededShuffle(42))->of(['a' => 1, 'b' => 2])))->toBeTrue();
    });
});
