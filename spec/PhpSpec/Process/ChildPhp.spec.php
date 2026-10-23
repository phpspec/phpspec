<?php

use PhpSpec\Process\ChildPhp;

describe(ChildPhp::class, function () {

    it('reports the fatal error a piece of code ends on, in its own words', function () {
        expect((new ChildPhp())->fatalOf('class Probed implements \UnitEnum {}'))->toBe('Non-enum class Probed cannot implement interface UnitEnum');
        expect((new ChildPhp())->fatalOf('class Probed implements \Traversable {}'))->toBe('Class Probed must implement interface Traversable as part of either Iterator or IteratorAggregate');
    });

    it('reports nothing for code that ends without one', function () {
        expect((new ChildPhp())->fatalOf('trigger_error("only a warning", E_USER_WARNING);'))->toBeNull();
    });
});
