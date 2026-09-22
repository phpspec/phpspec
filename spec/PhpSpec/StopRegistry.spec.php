<?php

use PhpSpec\Result\ExampleResult;
use PhpSpec\StopConditions;
use PhpSpec\StopRegistry;

describe(StopRegistry::class, function () {

    afterEach(function () {
        StopRegistry::reset();
    });

    it('never halts until conditions are activated', function () {
        expect(StopRegistry::reached(new ExampleResult('breaks', [], true)))->toBeFalse();
        expect(StopRegistry::halted())->toBeFalse();
    });

    it('halts at the first result meeting the conditions and stays halted', function () {
        StopRegistry::activate(new StopConditions(onError: true));

        expect(StopRegistry::reached(new ExampleResult('passes', [])))->toBeFalse();
        expect(StopRegistry::reached(new ExampleResult('breaks', [], true)))->toBeTrue();
        expect(StopRegistry::reached(new ExampleResult('passes again', [])))->toBeTrue();
        expect(StopRegistry::halted())->toBeTrue();
    });

    it('starts afresh when activated again', function () {
        StopRegistry::activate(new StopConditions(onError: true));
        StopRegistry::reached(new ExampleResult('breaks', [], true));

        StopRegistry::activate(new StopConditions(onError: true));

        expect(StopRegistry::halted())->toBeFalse();
    });

    it('forgets the conditions on reset', function () {
        StopRegistry::activate(new StopConditions(onError: true));
        StopRegistry::reached(new ExampleResult('breaks', [], true));

        StopRegistry::reset();

        expect(StopRegistry::halted())->toBeFalse();
        expect(StopRegistry::reached(new ExampleResult('breaks', [], true)))->toBeFalse();
    });
});
