<?php

use PhpSpec\Extensions\ListenerExtension;
use PhpSpec\Result\Kind;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\State;

describe(ListenerExtension::class, function () {

    this('ended', fn() => fn(State $state): Outcome => new Outcome(Kind::Example, ['Calc', 'adds'], $state, null, null, null, null, 0.0));

    it('hears nothing it does not ask for', function () {
        $listener = new class extends ListenerExtension {};

        $listener->beforeSuite();
        foreach (State::cases() as $state) {
            $listener->completed(($this->ended)($state));
        }
        $listener->afterSuite();

        expect(true)->toBeTrue();
    });

    it('hears an outcome by how it ended: a pass, a failure, an error, work left pending or skipped', function () {
        $listener = new class extends ListenerExtension {
            /** @var list<string> */
            public array $heard = [];

            public function onPass(Outcome $outcome): void
            {
                $this->heard[] = 'pass ' . $outcome->state->value;
            }

            public function onFail(Outcome $outcome): void
            {
                $this->heard[] = 'fail ' . $outcome->state->value;
            }

            public function onError(Outcome $outcome): void
            {
                $this->heard[] = 'error ' . $outcome->state->value;
            }

            public function onPending(Outcome $outcome): void
            {
                $this->heard[] = 'pending ' . $outcome->state->value;
            }

            public function onSkipped(Outcome $outcome): void
            {
                $this->heard[] = 'skipped ' . $outcome->state->value;
            }
        };

        foreach (State::cases() as $state) {
            $listener->completed(($this->ended)($state));
        }

        expect($listener->heard)->toBe(['pass passed', 'fail failed', 'error errored', 'pending pending', 'skipped skipped', 'pass risky', 'fail undefined']);
    });
});
