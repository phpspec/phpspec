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

    // The reader has just written the example they were asked for. Telling
    // them to write one again reads as though it did not count.
    it('asks for more of the same example when one reaches part of the change', function () {
        $violation = Violation::partlyReached('src/Basket.php', [15, 16], 'Basket::applyCoupon');

        expect($violation->summary)->toBe('Part of the new logic in Basket::applyCoupon is still unreached.');
        expect($violation->remedy)->toBe('Extend your examples for Basket::applyCoupon to reach lines 15 and 16.');
    });

    it('names one unreached line as one line', function () {
        $violation = Violation::partlyReached('src/helpers.php', [12]);

        expect($violation->remedy)->toBe('Extend your examples to reach line 12 of src/helpers.php.');
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
