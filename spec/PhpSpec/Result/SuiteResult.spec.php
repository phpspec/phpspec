<?php

use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;

describe(SuiteResult::class, function() {

    it("is empty with no results", function() {
        $result = new SuiteResult([]);
        expect($result->isEmpty())->toBe(true);
        expect($result->getResults())->toHaveCount(0);
    });

    it("is not empty with results", function() {
        $spec = new SpecificationResult("spec", []);
        $result = new SuiteResult([$spec]);
        expect($result->isEmpty())->toBe(false);
        expect($result->getResults())->toHaveCount(1);
    });

    it("returns 0 when all examples pass", function() {
        $example = new ExampleResult("test", []);
        $spec = new SpecificationResult("spec", [$example]);
        $result = new SuiteResult([$spec]);
        expect($result->status())->toBe(0);
    });

    it("returns 1 when there are failures", function() {
        $failedMatch = \PhpSpec\Result\MatchResult::failed("a", "b", "fail", __FILE__, __LINE__);
        $example = new ExampleResult("test", [$failedMatch]);
        $spec = new SpecificationResult("spec", [$example]);
        $result = new SuiteResult([$spec]);
        expect($result->status())->toBe(1);
    });

    it("returns 1 when there are errors", function() {
        $example = new ExampleResult("test", [], true);
        $spec = new SpecificationResult("spec", [$example]);
        $result = new SuiteResult([$spec]);
        expect($result->status())->toBe(1);
    });

    it("tracks duration", function() {
        $result = new SuiteResult([]);
        expect($result->getDuration())->toBe(0.0);
        $result->setDuration(1.5);
        expect($result->getDuration())->toBe(1.5);
    });

    it("collects slowest examples", function() {
        $ex1 = new ExampleResult("fast", []);
        $ex1->setDuration(0.1);
        $ex2 = new ExampleResult("slow", []);
        $ex2->setDuration(0.5);
        $ex3 = new ExampleResult("medium", []);
        $ex3->setDuration(0.3);

        $spec = new SpecificationResult("spec", [$ex1, $ex2, $ex3]);
        $result = new SuiteResult([$spec]);

        $slowest = $result->getSlowestExamples(2);
        expect($slowest)->toHaveCount(2);
        expect($slowest[0]->getTitle())->toBe("slow");
        expect($slowest[1]->getTitle())->toBe("medium");
    });

    it("collects slowest examples from nested contexts", function() {
        $ex1 = new ExampleResult("top", []);
        $ex1->setDuration(0.1);
        $ex2 = new ExampleResult("nested", []);
        $ex2->setDuration(0.5);

        $ctx = new ContextResult("ctx", [$ex2]);
        $spec = new SpecificationResult("spec", [$ex1, $ctx]);
        $result = new SuiteResult([$spec]);

        $slowest = $result->getSlowestExamples(10);
        expect($slowest)->toHaveCount(2);
        expect($slowest[0]->getTitle())->toBe("nested");
    });

    it("skips pending examples in slowest", function() {
        $ex1 = new ExampleResult("passing", []);
        $ex1->setDuration(0.1);
        $ex2 = new ExampleResult("pending", [], false, true);
        $ex2->setDuration(0.5);

        $spec = new SpecificationResult("spec", [$ex1, $ex2]);
        $result = new SuiteResult([$spec]);

        $slowest = $result->getSlowestExamples(10);
        expect($slowest)->toHaveCount(1);
        expect($slowest[0]->getTitle())->toBe("passing");
    });

    it("getAllExamples collects from nested contexts", function() {
        $ex1 = new ExampleResult("top", []);
        $ex2 = new ExampleResult("nested", []);
        $ctx = new ContextResult("ctx", [$ex2]);
        $spec = new SpecificationResult("spec", [$ex1, $ctx]);
        $result = new SuiteResult([$spec]);

        $all = $result->getAllExamples();
        expect($all)->toHaveCount(2);
    });

    it("getAllExamples returns empty for empty suite", function() {
        $result = new SuiteResult([]);
        expect($result->getAllExamples())->toBe([]);
    });

    it("returns 0 status for empty suite", function() {
        $result = new SuiteResult([]);
        expect($result->status())->toBe(0);
    });

});
