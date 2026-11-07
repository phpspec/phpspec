<?php

use PhpSpec\Result\State;

describe(State::class, function () {

    it('names each state as the run counts it', function () {
        expect(array_map(fn(State $state) => $state->value, State::cases()))->toBe(['passed', 'failed', 'errored', 'pending', 'skipped', 'risky', 'undefined']);
    });

    it('fails the run for a failure, an error and an undefined step, and for nothing else', function () {
        $failing = array_filter(State::cases(), fn(State $state) => $state->failsTheRun());

        expect(array_values($failing))->toBe([State::Failed, State::Errored, State::Undefined]);
    });
});
