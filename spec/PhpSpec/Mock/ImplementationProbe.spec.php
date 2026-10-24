<?php

use PhpSpec\Mock\ImplementationProbe;

describe(ImplementationProbe::class, function () {

    it('finds the reason PHP refuses a plain class implementing a built-in interface, in its words and without the probe in them', function () {
        $probe = new ImplementationProbe();

        expect($probe->refusal(\UnitEnum::class))->toBe('Non-enum class cannot implement interface UnitEnum');
        expect($probe->refusal(\Throwable::class))->toBe('Class cannot implement interface Throwable, extend Exception or Error instead');
    });

    it('finds none for a built-in interface a plain class can implement', function () {
        $probe = new ImplementationProbe();

        expect($probe->refusal(\Countable::class))->toBeNull();
        expect($probe->refusal(\IteratorAggregate::class))->toBeNull();
    });

    it('asks about an interface of any extension, a bundled one included, since a release may add a rule', function () {
        $asked = new ImplementationProbe();

        expect($asked->refusal(\JsonSerializable::class))->toBeNull();
        expect($asked->refusal(\UnitEnum::class))->toBe('Non-enum class cannot implement interface UnitEnum');
    });
});
