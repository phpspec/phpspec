<?php

use PhpSpec\Coverage\Percentage;

describe(Percentage::class, function () {
    it('is cut to the places asked, never rounded up to more than was covered', function () {
        expect((new Percentage(2502, 2503))->cut())->toBe('99.9');
        expect((new Percentage(2502, 2503))->cut(2))->toBe('99.96');
        expect((new Percentage(2, 3))->cut())->toBe('66.6');
        expect((new Percentage(1, 1))->cut())->toBe('100.0');
        expect((new Percentage(29, 100))->cut())->toBe('29.0');
    });

    it('is nothing of nothing', function () {
        expect((new Percentage(0, 0))->cut())->toBe('0.0');
        expect((new Percentage(0, 0))->value())->toBe(0.0);
    });

    it('reaches a threshold by the counts, with no float in the way', function () {
        expect((new Percentage(29, 100))->reaches(29.0))->toBeTrue();
        expect((new Percentage(2502, 2503))->reaches(99.95))->toBeTrue();
        expect((new Percentage(2502, 2503))->reaches(100.0))->toBeFalse();
    });

    it('is complete only when every line is covered', function () {
        expect((new Percentage(3, 3))->complete())->toBeTrue();
        expect((new Percentage(2502, 2503))->complete())->toBeFalse();
    });
});
