<?php

use PhpSpec\Guard\Violation;

describe(Violation::class, function () {

    it('names the member whose logic no example reaches', function () {
        $violation = Violation::untestedLogic('src/Basket.php', [7, 8], 'Basket::applyCoupon');

        expect($violation->summary)->toBe('New logic in Basket::applyCoupon is untested.');
        expect($violation->remedy)->toBe('Write an example for Basket::applyCoupon, then make it pass.');
    });

    it('points at the file when the source names no member', function () {
        $violation = Violation::untestedLogic('src/helpers.php', [12]);

        expect($violation->summary)->toBe('New logic in src/helpers.php:12 is untested.');
        expect($violation->remedy)->toBe('Write an example that reaches src/helpers.php, then make it pass.');
    });

    it('hands itself over as data', function () {
        $violation = Violation::untestedLogic('src/Basket.php', [7], 'Basket::applyCoupon');

        expect($violation->toArray())->toBe([
            'file' => 'src/Basket.php',
            'lines' => [7],
            'member' => 'Basket::applyCoupon',
            'remedy' => 'Write an example for Basket::applyCoupon, then make it pass.',
        ]);
    });

});
