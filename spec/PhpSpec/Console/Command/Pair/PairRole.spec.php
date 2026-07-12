<?php

use PhpSpec\Console\Command\Pair\PairRole;

describe(PairRole::class, function () {

    it('has the human driving and the AI navigating by default', function () {
        expect(PairRole::HumanDrives->aiIsNavigator())->toBe(true);
        expect(PairRole::HumanDrives->aiIsDriver())->toBe(false);
    });

    it('has the AI driving when swapped', function () {
        expect(PairRole::AiDrives->aiIsDriver())->toBe(true);
        expect(PairRole::AiDrives->aiIsNavigator())->toBe(false);
    });

    it('maps each role to its prompt artifact', function () {
        expect(PairRole::HumanDrives->promptArtifact())->toBe('navigator');
        expect(PairRole::AiDrives->promptArtifact())->toBe('driver');
    });

    it('swaps to the other role', function () {
        expect(PairRole::HumanDrives->swapped())->toBe(PairRole::AiDrives);
        expect(PairRole::AiDrives->swapped())->toBe(PairRole::HumanDrives);
    });

    it('announces a one-line contract for each role', function () {
        expect(PairRole::AiDrives->contractLine())->toContain("I'm driving");
        expect(PairRole::HumanDrives->contractLine())->toContain("You're driving");
    });

});
