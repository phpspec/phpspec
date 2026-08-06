<?php

use PhpSpec\Guard\Verdict;
use PhpSpec\Guard\Violation;

describe(Verdict::class, function () {

    it('holds when nothing was left unspecified', function () {
        $verdict = Verdict::clean();

        expect($verdict->held())->toBeTrue();
        expect($verdict->toArray())->toBe(['held' => true, 'violations' => []]);
    });

    it('hands the same verdict over as data', function () {
        $verdict = new Verdict([Violation::untestedLogic('src/Basket.php', [7], 'Basket::applyCoupon')]);

        expect($verdict->held())->toBeFalse();
        expect($verdict->toArray())->toBe([
            'held' => false,
            'violations' => [[
                'file' => 'src/Basket.php',
                'lines' => [7],
                'member' => 'Basket::applyCoupon',
                'remedy' => 'Write an example for Basket::applyCoupon, then make it pass.',
            ]],
        ]);
    });

    it('keeps every violation it was given', function () {
        $verdict = new Verdict([
            Violation::untestedLogic('src/Basket.php', [7], 'Basket::applyCoupon'),
            Violation::untestedLogic('src/helpers.php', [12]),
        ]);

        expect($verdict->violations())->toHaveCount(2);
    });

});
