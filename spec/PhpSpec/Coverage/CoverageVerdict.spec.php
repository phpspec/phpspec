<?php

use PhpSpec\Coverage\CoverageVerdict;
use PhpSpec\Coverage\Percentage;

describe(CoverageVerdict::class, function () {
    it('is met with no threshold to fall short of', function () {
        expect((new CoverageVerdict(new Percentage(1, 10)))->met())->toBeTrue();
    });

    it('is met when the covered lines reach the threshold, and only then', function () {
        expect((new CoverageVerdict(new Percentage(29, 100), 29.0))->met())->toBeTrue();
        expect((new CoverageVerdict(new Percentage(2502, 2503), 100.0))->met())->toBeFalse();
    });

    it('cannot be met while a source file with no coverage recorded is missing from the count', function () {
        $verdict = new CoverageVerdict(new Percentage(1, 1), 100.0, ['src/Untested.php']);

        expect($verdict->met())->toBeFalse();
        expect($verdict->exitCode())->toBe(1);
    });

    it('shows the percentage cut to the places the threshold is written to, so it reads below it exactly when it is', function () {
        $short = new CoverageVerdict(new Percentage(2502, 2503), 100.0);
        $fine = new CoverageVerdict(new Percentage(2502, 2503), 99.95);

        expect([$short->shown(), $short->shownRequired()])->toBe(['99.9', '100.0']);
        expect([$fine->shown(), $fine->shownRequired()])->toBe(['99.96', '99.95']);
        expect((new CoverageVerdict(new Percentage(2, 3)))->shown())->toBe('66.6');
    });
});
