<?php

use PhpSpec\Mock\ArgumentMatcher;
use PhpSpec\Mock\Stubs;

describe(Stubs::class, function () {
    this('stubs', fn() => new Stubs());

    it('answers nothing where no stub matches the method or the arguments', function () {
        $this->stubs->add('find', [1], 'value', 'one');

        expect($this->stubs->answer('find', [2], true))->toBeNull();
        expect($this->stubs->answer('save', [1], true))->toBeNull();
    });

    it('matches by the arguments written, a matcher standing for values and none written for any', function () {
        $this->stubs->add('find', [ArgumentMatcher::type('string')], 'value', 'named');
        $this->stubs->add('save', null, 'value', 'saved');

        expect($this->stubs->answer('find', ['tea'], true))->toBe(['type' => 'value', 'data' => 'named']);
        expect($this->stubs->answer('save', [1, 2], true))->toBe(['type' => 'value', 'data' => 'saved']);
    });

    it('answers the code under spec with each matching stub in the order declared, the last one answering on', function () {
        $this->stubs->add('pay', [1], 'throw', ['class' => RuntimeException::class, 'message' => 'Declined']);
        $this->stubs->add('pay', [ArgumentMatcher::any()], 'value', 5);

        expect($this->stubs->answer('pay', [1], true)['type'] ?? null)->toBe('throw');
        expect($this->stubs->answer('pay', [1], true))->toBe(['type' => 'value', 'data' => 5]);
        expect($this->stubs->answer('pay', [1], true))->toBe(['type' => 'value', 'data' => 5]);
        expect($this->stubs->answer('pay', [2], true))->toBe(['type' => 'value', 'data' => 5]);
    });

    it('lets the spec see what the next stub would answer without using it up, an exception withheld', function () {
        $this->stubs->add('pay', [1], 'value', 5);
        $this->stubs->add('pay', [1], 'throw', ['class' => RuntimeException::class, 'message' => 'Declined']);

        expect($this->stubs->answer('pay', [1], false))->toBe(['type' => 'value', 'data' => 5]);
        expect($this->stubs->answer('pay', [1], true))->toBe(['type' => 'value', 'data' => 5]);
        expect($this->stubs->answer('pay', [1], false)['type'] ?? null)->toBe('withheld');
    });
});
