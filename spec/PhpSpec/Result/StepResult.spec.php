<?php

use PhpSpec\Result\StepResult;
use PhpSpec\StoryBDD\StepError;

describe(StepResult::class, function () {

    it("tracks its title", function () {
        $result = new StepResult("Given a step", "passed");
        expect($result->getTitle())->toBe("Given a step");
    });

    it("returns empty results array", function () {
        $result = new StepResult("Given a step", "passed");
        expect($result->getResults())->toBe([]);
    });

    it("detects passed state", function () {
        $result = new StepResult("Given a step", "passed");
        expect($result->isPassed())->toBeTrue();
        expect($result->isFailure())->toBeFalse();
    });

    it("detects failed state", function () {
        $result = new StepResult("Given a step", "failure");
        expect($result->isFailure())->toBeTrue();
        expect($result->isPassed())->toBeFalse();
    });

    it("detects pending state", function () {
        $result = new StepResult("Given a step", "pending");
        expect($result->isPending())->toBeTrue();
    });

    it("detects undefined state", function () {
        $result = new StepResult("Given a step", "undefined");
        expect($result->isUndefined())->toBeTrue();
    });

    it("detects skipped state", function () {
        $result = new StepResult("Given a step", "skipped");
        expect($result->isSkipped())->toBeTrue();
    });

    it("returns the state string", function () {
        $result = new StepResult("step", "pending");
        expect($result->getState())->toBe("pending");
    });

    it("stores and retrieves an error", function () {
        $result = new StepResult("step", "failure");
        $error = new StepError("boom", new \RuntimeException("boom"));
        $result->setError($error);
        expect($result->getError()->getMessage())->toBe("boom");
        expect($result->getError()->getType())->toBe('RuntimeException');
    });

    it("returns null error by default", function () {
        $result = new StepResult("step", "passed");
        expect($result->getError())->toBeNull();
    });

});
