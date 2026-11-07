<?php

use PhpSpec\Result\Kind;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\State;

describe(Outcome::class, function () {

    it('is named by every title that leads to it, and titled by its own', function () {
        $outcome = new Outcome(Kind::Example, ['Calc', 'when empty', 'adds'], State::Passed, null, null, null, null, 0.0);

        expect($outcome->name())->toBe('Calc > when empty > adds');
        expect($outcome->title())->toBe('adds');
    });
});
