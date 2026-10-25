<?php

use PhpSpec\Coverage\Threshold;

describe(Threshold::class, function () {
    it('counts the places it is written to', function () {
        expect((new Threshold(100.0))->places())->toBe(0);
        expect((new Threshold(64.4))->places())->toBe(1);
        expect((new Threshold(90.18))->places())->toBe(2);
    });

    it('is a whole number of its last place, whatever float stands for it', function () {
        expect((new Threshold(90.18))->units())->toBe(9018);
        expect((new Threshold(64.4))->units())->toBe(644);
        expect((new Threshold(100.0))->units())->toBe(100);
    });
});
