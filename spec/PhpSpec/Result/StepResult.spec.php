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

    it("carries the reason it was left pending or skipped for, and none unless given one", function () {
        expect((new StepResult("When I pay", "pending", "Needs the payment gateway"))->getReason())->toBe("Needs the payment gateway");
        expect((new StepResult("When I pay", "skipped", "No printer here"))->getReason())->toBe("No printer here");
        expect((new StepResult("When I pay", "skipped"))->getReason())->toBeNull();
    });

    it("tells a step that skipped itself from one skipped behind another", function () {
        expect((new StepResult("Given a printer", "skipped", "No printer here"))->isSkippedForAReason())->toBeTrue();
        expect((new StepResult("Then it prints", "skipped"))->isSkippedForAReason())->toBeFalse();
        expect((new StepResult("Given a printer", "pending", "later"))->isSkippedForAReason())->toBeFalse();
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

    it("files the notes raised while it ran under their kinds, each once however often it was raised", function () {
        $result = new StepResult("Given a step", "passed");
        $deprecated = ['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => 'a.php', 'line' => 3];

        $result->raised([
            ['severity' => E_USER_WARNING, 'message' => 'oops', 'file' => 'a.php', 'line' => 1],
            $deprecated,
            $deprecated,
            ['severity' => E_NOTICE, 'message' => 'info', 'file' => 'a.php', 'line' => 5],
        ]);

        expect(array_column($result->getWarnings(), 'message'))->toBe(['oops']);
        expect($result->getDeprecations())->toBe([$deprecated]);
        expect(array_column($result->getNotices(), 'message'))->toBe(['info']);
        expect($result->hasNotices())->toBeTrue();
    });

    it("records how long it ran", function () {
        $result = new StepResult("step", "passed");
        expect($result->getDuration())->toBe(0.0);

        $result->setDuration(0.25);
        expect($result->getDuration())->toBe(0.25);
    });

});
