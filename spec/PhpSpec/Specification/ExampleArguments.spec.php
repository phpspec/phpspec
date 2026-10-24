<?php

use PhpSpec\Specification\ExampleArguments;
use PhpSpec\Specification\Subject;

interface ExampleArgumentsSpecClock
{
    public function now(): int;
}

final class ExampleArgumentsSpecFixedClock implements ExampleArgumentsSpecClock
{
    public function now(): int
    {
        return 1;
    }
}

describe(ExampleArguments::class, function () {
    it('hands a closure a double of each parameter typed with a class or interface', function () {
        $arguments = new ExampleArguments();

        [$clock] = $arguments->for(fn (ExampleArgumentsSpecClock $clock, int $times) => null);

        expect($clock)->toBeAnInstanceOf(ExampleArgumentsSpecClock::class);
        expect($clock)->not()->toBeAnInstanceOf(ExampleArgumentsSpecFixedClock::class);
    });

    it('hands every closure naming a parameter alike the same double', function () {
        $arguments = new ExampleArguments();

        [$first] = $arguments->for(fn (ExampleArgumentsSpecClock $clock) => null);
        [$second] = $arguments->for(fn (ExampleArgumentsSpecClock $clock) => null);

        expect($second)->toBe($first);
    });

    it('hands a double, whatever $this holds under the name', function () {
        $world = new Subject();
        $world->clock = new ExampleArgumentsSpecFixedClock();
        $arguments = new ExampleArguments($world);

        [$clock] = $arguments->for(fn (ExampleArgumentsSpecClock $clock) => null);

        expect($clock)->not()->toBe($world->clock);
    });

    it('puts nothing on $this for a hook or an example', function () {
        $world = new Subject();

        (new ExampleArguments($world))->for(fn (ExampleArgumentsSpecClock $clock) => null);

        expect(isset($world->clock))->toBeFalse();
    });

    it('puts a double a this() takes on $this under its name, the one every closure naming it is handed', function () {
        $world = new Subject();
        $arguments = new ExampleArguments($world);

        [$clock] = $arguments->forThis(fn (ExampleArgumentsSpecClock $clock) => null);

        expect($world->clock)->toBe($clock);
        expect($arguments->for(fn (ExampleArgumentsSpecClock $clock) => null))->toBe([$clock]);
    });

    it('leaves $this to the this() that has the name', function () {
        $world = new Subject();
        $arguments = new ExampleArguments($world, ['clock']);

        $arguments->forThis(fn (ExampleArgumentsSpecClock $clock) => null);

        expect(isset($world->clock))->toBeFalse();
    });

    it('takes off $this what it put there, and nothing else', function () {
        $world = new Subject();
        $arguments = new ExampleArguments($world);
        $arguments->forThis(fn (ExampleArgumentsSpecClock $spare) => null);
        $arguments->for(fn (ExampleArgumentsSpecClock $timer) => null);
        $world->timer = new ExampleArgumentsSpecFixedClock();

        $arguments->forget();

        expect(isset($world->spare))->toBeFalse();
        expect($world->timer)->toBeAnInstanceOf(ExampleArgumentsSpecFixedClock::class);
    });
});
