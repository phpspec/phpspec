<?php

use PhpSpec\Mock\HeldCalls;
use PhpSpec\Mock\UnexpectedCallException;

describe(HeldCalls::class, function () {

    beforeEach(fn () => HeldCalls::forget());
    afterEach(fn () => HeldCalls::forget());

    this('refusal', fn () => fn (string $call): UnexpectedCallException => new UnexpectedCallException($call));

    it('gives back the refusal of the first call no check claimed, and holds nothing after', function () {
        $double = new stdClass();
        HeldCalls::hold($double, 'notify', ['Ann'], ($this->refusal)('notify("Ann")'));
        HeldCalls::hold($double, 'notify', ['Bob'], ($this->refusal)('notify("Bob")'));

        expect(HeldCalls::take()?->getMessage())->toBe('notify("Ann")');
        expect(HeldCalls::take())->toBeNull();
    });

    it('lets a check claim the calls its arguments match, on its own double alone', function () {
        $double = new stdClass();
        $other = new stdClass();
        HeldCalls::hold($double, 'notify', ['Ann'], ($this->refusal)('notify("Ann")'));
        HeldCalls::hold($other, 'notify', ['Ann'], ($this->refusal)('other notify("Ann")'));
        HeldCalls::hold($double, 'notify', ['Bob'], ($this->refusal)('notify("Bob")'));

        HeldCalls::claim($double, 'notify', ['Ann']);

        expect(HeldCalls::take()?->getMessage())->toBe('other notify("Ann")');
    });

    it('lets a check that names no arguments claim every call to its method', function () {
        $double = new stdClass();
        HeldCalls::hold($double, 'notify', ['Ann'], ($this->refusal)('notify("Ann")'));
        HeldCalls::hold($double, 'notify', ['Bob'], ($this->refusal)('notify("Bob")'));

        HeldCalls::claim($double, 'notify', null);

        expect(HeldCalls::take())->toBeNull();
    });
});