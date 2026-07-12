<?php

use PhpSpec\Console\Command\Pair\PairRole;
use PhpSpec\Console\Command\Pair\RoleState;

describe(RoleState::class, function () {

    it('starts with the human driving', function () {
        expect((new RoleState())->current())->toBe(PairRole::HumanDrives);
    });

    it('swaps to the other role and returns the new one', function () {
        $state = new RoleState();

        expect($state->swap())->toBe(PairRole::AiDrives);
        expect($state->current())->toBe(PairRole::AiDrives);
        expect($state->swap())->toBe(PairRole::HumanDrives);
        expect($state->current())->toBe(PairRole::HumanDrives);
    });

});
