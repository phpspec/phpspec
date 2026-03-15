<?php

use PhpSpec\StopConditions;

describe(StopConditions::class, function () {

    it("defaults to all false", function () {
        $stop = new StopConditions();
        expect($stop->onFailure)->toBeFalse();
        expect($stop->onError)->toBeFalse();
        expect($stop->onWarning)->toBeFalse();
        expect($stop->onDeprecation)->toBeFalse();
        expect($stop->onNotice)->toBeFalse();
        expect($stop->onSkipped)->toBeFalse();
        expect($stop->any())->toBeFalse();
    });

    it("reports any() true when onFailure is set", function () {
        $stop = new StopConditions(onFailure: true);
        expect($stop->any())->toBeTrue();
    });

    it("reports any() true when onWarning is set", function () {
        $stop = new StopConditions(onWarning: true);
        expect($stop->any())->toBeTrue();
    });

    it("creates fromProblems with all flags set", function () {
        $stop = StopConditions::fromProblems();
        expect($stop->onFailure)->toBeTrue();
        expect($stop->onError)->toBeTrue();
        expect($stop->onWarning)->toBeTrue();
        expect($stop->onDeprecation)->toBeTrue();
        expect($stop->onNotice)->toBeTrue();
        expect($stop->onSkipped)->toBeTrue();
        expect($stop->any())->toBeTrue();
    });

});
