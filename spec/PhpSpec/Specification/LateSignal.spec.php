<?php

use PhpSpec\Specification\LateSignal;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;

describe(LateSignal::class, function () {

    it('says the signal came after what it meant to stop had run, and where to call it instead', function () {
        $late = new LateSignal(new SkippedException('no service'), 'afterEach', 'the example', 'beforeEach or in the example');

        expect($late->sentence())->toBe('skip() in afterEach comes after the example ran; call it in beforeEach or in the example.');
    });

    it('names pending() for a pending signal', function () {
        $late = new LateSignal(new PendingException('later'), 'afterAll', 'the examples', 'beforeAll or in an example');

        expect($late->sentence())->toBe('pending() in afterAll comes after the examples ran; call it in beforeAll or in an example.');
    });

    it('keeps the signal, so the error points where it was raised', function () {
        $signal = new SkippedException('no service');

        expect((new LateSignal($signal, 'afterStep', 'the step', 'beforeStep or in the step'))->signal())->toBe($signal);
    });
});