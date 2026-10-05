<?php

use PhpSpec\Report\Formatter\Agent\FirstDifference;

describe(FirstDifference::class, function () {

    it('names the offset where two strings part, with what each says from there', function () {
        expect(FirstDifference::between("total: 0\n", "total: 0"))->toBe([
            'offset' => 8,
            'expected' => "\n",
            'actual' => '',
        ]);
    });

    it('shows a window of each string from the offset, not the whole of them', function () {
        $expected = str_repeat('a', 50) . 'EXPECTED-' . str_repeat('x', 100);
        $actual = str_repeat('a', 50) . 'ACTUAL-' . str_repeat('x', 100);

        $difference = FirstDifference::between($expected, $actual);

        expect($difference['offset'])->toBe(50);
        expect($difference['expected'])->toBe('EXPECTED-' . str_repeat('x', 31));
        expect($difference['actual'])->toBe('ACTUAL-' . str_repeat('x', 33));
    });

    it('names the path where two arrays part', function () {
        $difference = FirstDifference::between(['a' => 1, 'b' => [1, 2, 3]], ['a' => 1, 'b' => [1, 5, 3]]);

        expect($difference)->toBe(['path' => 'b[1]', 'expected' => 2, 'actual' => 5]);
    });

    it('leaves two arrays of different shape alone at the top, and names the key that holds them deeper down', function () {
        expect(FirstDifference::between(['a' => 1, 'b' => 2], ['a' => 1]))->toBeNull();
        expect(FirstDifference::between(['a' => ['x' => 1]], ['a' => ['y' => 1]]))->toBe(['path' => 'a', 'expected' => ['x' => 1], 'actual' => ['y' => 1]]);
    });

    it('has nothing to say about equal values, or values that are not both strings or both arrays', function () {
        expect(FirstDifference::between('same', 'same'))->toBeNull();
        expect(FirstDifference::between(1, 2))->toBeNull();
        expect(FirstDifference::between('1', 1))->toBeNull();
    });
});
