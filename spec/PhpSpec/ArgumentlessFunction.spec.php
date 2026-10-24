<?php

use PhpSpec\ArgumentlessFunction;

final class ArgumentlessFunctionSpecLamp
{
    public bool $lit = true;
}

describe(ArgumentlessFunction::class, function () {
    it('refuses a function that declares an argument it must be given', function () {
        expect(fn () => (new ArgumentlessFunction(function ($log) {}))())->toThrow(
            LogicException::class,
            'Arguments not allowed in this function, only this(), it(), beforeEach() and afterEach() can take doubles.',
        );
    });

    it('calls a function whose arguments all have defaults', function () {
        expect((new ArgumentlessFunction(fn (int $tries = 3) => $tries))())->toBe(3);
    });

    it('calls it on an object as $this', function () {
        $world = new ArgumentlessFunctionSpecLamp();

        expect((new ArgumentlessFunction(fn () => $this->lit))->callOn($world))->toBe(true);
    });
});
