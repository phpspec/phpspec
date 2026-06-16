<?php

use PhpSpec\Result\Counts;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;

describe(Counts::class, function() {

    it("counts passing examples", function() {
        $example1 = new ExampleResult("test1", [MatchResult::passed()]);
        $example2 = new ExampleResult("test2", [MatchResult::passed()]);
        $spec = new SpecificationResult("spec", [$example1, $example2]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['examples'])->toBe(2);
        expect($c['passes'])->toBe(2);
        expect($c['failures'])->toBe(0);
    });

    it("counts failures", function() {
        $passing = new ExampleResult("pass", [MatchResult::passed()]);
        $failing = new ExampleResult("fail", [MatchResult::failed("a", "b", "msg", __FILE__, __LINE__)]);
        $spec = new SpecificationResult("spec", [$passing, $failing]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['passes'])->toBe(1);
        expect($c['failures'])->toBe(1);
    });

    it("counts errors", function() {
        $errored = new ExampleResult("err", [], true);
        $spec = new SpecificationResult("spec", [$errored]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['errors'])->toBe(1);
        expect($c['passes'])->toBe(0);
    });

    it("counts pending examples", function() {
        $pending = new ExampleResult("pend", [], false, true);
        $passing = new ExampleResult("pass", [MatchResult::passed()]);
        $spec = new SpecificationResult("spec", [$pending, $passing]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['pending'])->toBe(1);
        expect($c['passes'])->toBe(1);
        expect($c['examples'])->toBe(2);
    });

    it("counts warnings", function() {
        $warned = new ExampleResult("warn", [MatchResult::passed()]);
        $warned->setWarnings([['severity' => E_WARNING, 'message' => 'test', 'file' => __FILE__, 'line' => __LINE__]]);
        $normal = new ExampleResult("ok", [MatchResult::passed()]);
        $spec = new SpecificationResult("spec", [$warned, $normal]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['warnings'])->toBe(1);
        expect($c['passes'])->toBe(2);
    });

    it("counts nested context results", function() {
        $ex = new ExampleResult("nested", [MatchResult::passed()]);
        $ctx = new ContextResult("ctx", [$ex]);
        $spec = new SpecificationResult("spec", [$ctx]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['examples'])->toBe(1);
        expect($c['passes'])->toBe(1);
        expect($c['specs'])->toBe(1);
    });

    it("counts feature results with scenarios and steps", function () {
        $steps = [
            new StepResult("Given a step", "passed"),
            new StepResult("When action", "passed"),
            new StepResult("Then result", "failure"),
        ];
        $scenario = new ScenarioResult("Scenario 1", $steps);
        $feature = new FeatureResult("Feature 1", [$scenario]);
        $results = new SuiteResult([$feature]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['features'])->toBe(1);
        expect($c['scenarios'])->toBe(1);
        expect($c['steps'])->toBe(3);
        expect($c['stepPasses'])->toBe(2);
        expect($c['stepFailures'])->toBe(1);
    });

    it("counts skipped examples", function () {
        $skipped = new ExampleResult("skip", [], false, false, true);
        $passing = new ExampleResult("pass", [MatchResult::passed()]);
        $spec = new SpecificationResult("spec", [$skipped, $passing]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['exampleSkipped'])->toBe(1);
        expect($c['passes'])->toBe(1);
        expect($c['examples'])->toBe(2);
    });

    it("counts deprecations", function () {
        $deprecated = new ExampleResult("dep", [MatchResult::passed()]);
        $deprecated->setDeprecations([['severity' => E_DEPRECATED, 'message' => 'old', 'file' => __FILE__, 'line' => __LINE__]]);
        $spec = new SpecificationResult("spec", [$deprecated]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['deprecations'])->toBe(1);
    });

    it("counts notices", function () {
        $noticed = new ExampleResult("note", [MatchResult::passed()]);
        $noticed->setNotices([['severity' => E_NOTICE, 'message' => 'info', 'file' => __FILE__, 'line' => __LINE__]]);
        $spec = new SpecificationResult("spec", [$noticed]);
        $results = new SuiteResult([$spec]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['notices'])->toBe(1);
    });

    it("counts pending, undefined, and skipped steps", function () {
        $steps = [
            new StepResult("Given pending", "pending"),
            new StepResult("When undefined", "undefined"),
            new StepResult("Then skipped", "skipped"),
        ];
        $scenario = new ScenarioResult("Scenario", $steps);
        $feature = new FeatureResult("Feature", [$scenario]);
        $results = new SuiteResult([$feature]);

        $counts = new Counts($results);
        $c = $counts->toArray();
        expect($c['pending'])->toBe(1);
        expect($c['undefined'])->toBe(1);
        expect($c['skipped'])->toBe(1);
        expect($c['steps'])->toBe(3);
    });

});
