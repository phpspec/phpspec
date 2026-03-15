<?php

use PhpSpec\Report\Formatter\Pretty;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Pretty::class, function() {

    it("formats passing results", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("passes", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("passes");
    });

    it("formats failing results with details", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed("actual_val", "expected_val", "Expected a to be b", __FILE__, __LINE__);
        $example = new ExampleResult("fails here", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("fails here");
        expect($text)->toContain("Failure");
        expect($text)->toContain("Expected a to be b");
    });

    it("formats error results with details", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("errors out", [], true);
        $example->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("errors out");
        expect($text)->toContain("boom");
    });

    it("formats pending results", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("is pending", [], false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("pending");
    });

    it("formats empty suite", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $suite = new SuiteResult([]);
        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("No specs found");
    });

    it("formats results with context", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("inner test", [$match]);
        $ctx = new ContextResult("my context", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("my context");
        expect($text)->toContain("inner test");
    });

    it("formats context with error", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $ctx = new ContextResult("errored context", []);
        $error = new ExampleError("context broke", new \RuntimeException("context broke"));
        $ctx->setError($error);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("errored context");
        expect($text)->toContain("context broke");
    });

    it("formats results in verbose mode", function() {
        $output = new BufferedOutput();
        $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("verbose test", [$match]);
        $example->setDuration(0.123);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("verbose test");
        expect($text)->toContain("ms");
    });

    it("formats results with warnings", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("warns", [$match]);
        $example->setWarnings([
            ['severity' => E_WARNING, 'message' => 'test warning msg', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("test warning msg");
    });

    it("formats failure with expected/actual values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed("hello", "world", "Expected hello to be world", __FILE__, __LINE__);
        $example = new ExampleResult("value mismatch", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
        expect($text)->toContain("got:");
    });

    it("formats nested context errors", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $failMatch = MatchResult::failed("a", "b", "inner fail", __FILE__, __LINE__);
        $example = new ExampleResult("nested fail", [$failMatch]);
        $ctx = new ContextResult("inner ctx", [$example]);
        $spec = new SpecificationResult("OuterSpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("inner ctx");
        expect($text)->toContain("nested fail");
    });

    it("formats failure with boolean values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed(true, false, "Expected true to be false", __FILE__, __LINE__);
        $example = new ExampleResult("bool mismatch", [$match]);
        $spec = new SpecificationResult("BoolSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
        expect($text)->toContain("got:");
    });

    it("formats failure with null value", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed("something", null, "Expected something to be null", __FILE__, __LINE__);
        $example = new ExampleResult("null check", [$match]);
        $spec = new SpecificationResult("NullSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("null");
    });

    it("formats failure with array values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed([1, 2], [3, 4], "Expected arrays to match", __FILE__, __LINE__);
        $example = new ExampleResult("array mismatch", [$match]);
        $spec = new SpecificationResult("ArraySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
    });

    it("formats failure with object values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $match = MatchResult::failed($obj1, $obj2, "Expected objects to match", __FILE__, __LINE__);
        $example = new ExampleResult("obj mismatch", [$match]);
        $spec = new SpecificationResult("ObjSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("stdClass");
    });

    it("formats failure with numeric values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed(42, 99, "Expected 42 to be 99", __FILE__, __LINE__);
        $example = new ExampleResult("num mismatch", [$match]);
        $spec = new SpecificationResult("NumSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
    });

    it("formats a feature with passing steps", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $steps = [
            new StepResult("Given a step", "passed"),
            new StepResult("When action", "passed"),
            new StepResult("Then result", "passed"),
        ];
        $scenario = new ScenarioResult("Say hello", $steps);
        $feature = new FeatureResult("Greeting", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Feature: Greeting");
        expect($text)->toContain("Say hello");
        expect($text)->toContain("Given a step");
    });

    it("formats a feature with failed step and error", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $failedStep = new StepResult("When it breaks", "failure");
        $failedStep->setError(new StepError("something broke", new \RuntimeException("something broke")));
        $skippedStep = new StepResult("Then skipped", "skipped");

        $scenario = new ScenarioResult("Broken", [$failedStep, $skippedStep]);
        $feature = new FeatureResult("Errors", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Feature: Errors");
        expect($text)->toContain("something broke");
    });

    it("formats a feature with undefined and pending steps", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $steps = [
            new StepResult("Given undefined", "undefined"),
            new StepResult("When pending", "pending"),
        ];
        $scenario = new ScenarioResult("Mixed", $steps);
        $feature = new FeatureResult("States", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Feature: States");
        expect($text)->toContain("undefined");
        expect($text)->toContain("pending");
    });

    it("formats feature counts correctly", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $steps = [
            new StepResult("Given passed", "passed"),
            new StepResult("Then failed", "failure"),
        ];
        $scenario = new ScenarioResult("Test", $steps);
        $feature = new FeatureResult("Feature", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("1 feature");
        expect($text)->toContain("1 scenario");
        expect($text)->toContain("2 steps");
    });

});
