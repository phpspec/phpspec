<?php

use PhpSpec\Coverage\CoverageVerdict;

describe(CoverageVerdict::class, function () {
    it('is met with no threshold to fall short of', function () {
        expect((new CoverageVerdict(10.0))->met())->toBeTrue();
    });

    it('is met when the percentage reaches the threshold', function () {
        expect((new CoverageVerdict(90.0, 90.0))->met())->toBeTrue();
        expect((new CoverageVerdict(89.9, 90.0))->met())->toBeFalse();
    });

    it('cannot be met while a source file with no coverage recorded is missing from the count', function () {
        $verdict = new CoverageVerdict(100.0, 100.0, ['src/Untested.php']);

        expect($verdict->met())->toBeFalse();
        expect($verdict->exitCode())->toBe(1);
    });
});
