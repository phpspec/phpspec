<?php

use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Specification\ExampleError;

describe(ExampleResult::class, function() {

    it("is not a failure with passing matches", function() {
        $match = MatchResult::passed();
        $result = new ExampleResult("test", [$match]);
        expect($result->isFailure())->toBe(false);
        expect($result->isError())->toBe(false);
    });

    it("is a failure when a match fails", function() {
        $passing = MatchResult::passed();
        $failing = MatchResult::failed("a", "b", "fail", __FILE__, __LINE__);

        $result = new ExampleResult("test", [$passing, $failing]);
        expect($result->isFailure())->toBe(true);
    });

    it("is an error when flagged", function() {
        $result = new ExampleResult("test", [], true);
        expect($result->isError())->toBe(true);
    });

    it("tracks its title", function() {
        $result = new ExampleResult("my test", []);
        expect($result->getTitle())->toBe("my test");
    });

    it("returns its match results", function() {
        $match = MatchResult::passed();
        $result = new ExampleResult("test", [$match]);
        expect($result->getResults())->toHaveCount(1);
    });

    it("stores an error object", function() {
        $result = new ExampleResult("test", [], true);
        $error = new ExampleError("broke", new \RuntimeException("broke"));
        $result->setError($error);
        expect($result->getError())->toBeAnInstanceOf(ExampleError::class);
    });

    it("is pending when flagged", function() {
        $result = new ExampleResult("test", [], false, true);
        expect($result->isPending())->toBe(true);
    });

    it("is not pending by default", function() {
        $result = new ExampleResult("test", []);
        expect($result->isPending())->toBe(false);
    });

    it("tracks duration", function() {
        $result = new ExampleResult("test", []);
        expect($result->getDuration())->toBe(0.0);
        $result->setDuration(0.123);
        expect($result->getDuration())->toBe(0.123);
    });

    it("tracks warnings", function() {
        $result = new ExampleResult("test", []);
        expect($result->hasWarnings())->toBe(false);
        expect($result->getWarnings())->toBe([]);

        $warnings = [['severity' => E_WARNING, 'message' => 'oops', 'file' => __FILE__, 'line' => __LINE__]];
        $result->setWarnings($warnings);
        expect($result->hasWarnings())->toBe(true);
        expect($result->getWarnings())->toHaveCount(1);
    });

    it("is skipped when flagged", function() {
        $result = new ExampleResult("test", [], false, false, true);
        expect($result->isSkipped())->toBe(true);
        expect($result->isPending())->toBe(false);
    });

    it("is not skipped by default", function() {
        $result = new ExampleResult("test", []);
        expect($result->isSkipped())->toBe(false);
    });

    it("tracks deprecations", function() {
        $result = new ExampleResult("test", []);
        expect($result->hasDeprecations())->toBe(false);
        expect($result->getDeprecations())->toBe([]);

        $deprecations = [['severity' => E_DEPRECATED, 'message' => 'old', 'file' => __FILE__, 'line' => __LINE__]];
        $result->setDeprecations($deprecations);
        expect($result->hasDeprecations())->toBe(true);
        expect($result->getDeprecations())->toHaveCount(1);
    });

    it("tracks notices", function() {
        $result = new ExampleResult("test", []);
        expect($result->hasNotices())->toBe(false);
        expect($result->getNotices())->toBe([]);

        $notices = [['severity' => E_NOTICE, 'message' => 'info', 'file' => __FILE__, 'line' => __LINE__]];
        $result->setNotices($notices);
        expect($result->hasNotices())->toBe(true);
        expect($result->getNotices())->toHaveCount(1);
    });

    it("is not a context", function() {
        $result = new ExampleResult("test", []);
        expect($result->isContext())->toBe(false);
    });

});
