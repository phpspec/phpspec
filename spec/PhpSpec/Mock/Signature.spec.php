<?php

use PhpSpec\Mock\Signature;

describe(Signature::class, function () {

    this('signature', fn() => new Signature('\App\Journal', 'log', 1, null, 'string $message, int ...$context', [
        ['message', 'string', static fn(string $value) => $value],
        ['context', 'int', static fn(int $value) => $value],
    ]));

    it('holds the named arguments a variadic collects to its type, keeping their names', function () {
        expect($this->signature->holdNamed(['user' => '7', 'page' => 2], 1, '/project/src/App/Journal.php', 3, false))->toBe(['user' => 7, 'page' => 2]);
    });

    it('refuses a named argument of the wrong type as PHP does, counting it after those given by position', function () {
        expect(fn() => $this->signature->holdNamed(['user' => 'ann'], 1, '/project/src/App/Journal.php', 3, false))
            ->toThrow(TypeError::class, 'App\Journal::log(): Argument #2 must be of type int, string given, called in /project/src/App/Journal.php on line 3');
    });

    it('holds a named argument strictly where the calling file declares strict types', function () {
        expect(fn() => $this->signature->holdNamed(['user' => '7'], 1, '/project/src/App/Journal.php', 3, true))->toThrow(TypeError::class);
    });
});
