<?php

use PhpSpec\Guard\Verdict;
use PhpSpec\Guard\Violation;

describe(Verdict::class, function () {

    it('holds when nothing was left unspecified', function () {
        $verdict = Verdict::clean();

        expect($verdict->judged())->toBeTrue();
        expect($verdict->held())->toBeTrue();
        expect($verdict->toArray())->toBe(['held' => true, 'judged' => true, 'violations' => []]);
    });

    // "I could not judge" is not "nothing was wrong", and a reader of either
    // the console or the document has to be able to tell the two apart.
    it('says when it judged nothing, and why', function () {
        $verdict = Verdict::cannotJudge('Guard is on but no baseline is recorded in this checkout.');

        expect($verdict->judged())->toBeFalse();
        expect($verdict->reason())->toBe('Guard is on but no baseline is recorded in this checkout.');
        expect($verdict->toArray())->toBe([
            'held' => true,
            'judged' => false,
            'violations' => [],
            'reason' => 'Guard is on but no baseline is recorded in this checkout.',
        ]);
    });

    it('hands the same verdict over as data', function () {
        $verdict = Verdict::of([Violation::untestedLogic('src/Basket.php', [7], 'Basket::applyCoupon')]);

        expect($verdict->held())->toBeFalse();
        expect($verdict->toArray())->toBe([
            'held' => false,
            'judged' => true,
            'violations' => [[
                'file' => 'src/Basket.php',
                'lines' => [7],
                'member' => 'Basket::applyCoupon',
                'remedy' => 'Write an example for Basket::applyCoupon, then make it pass.',
            ]],
        ]);
    });

    it('keeps every violation it was given', function () {
        $verdict = Verdict::of([
            Violation::untestedLogic('src/Basket.php', [7], 'Basket::applyCoupon'),
            Violation::untestedLogic('src/helpers.php', [12]),
        ]);

        expect($verdict->violations())->toHaveCount(2);
    });

});
