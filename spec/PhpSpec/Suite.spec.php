<?php

use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;
use PhpSpec\Specification;
use PhpSpec\StopConditions;
use PhpSpec\Suite;

describe(Suite::class, function() {

    let("suite", fn() => new Suite([]));

    it("instantiates", fn() => expect($this->suite)->toBeAnInstanceOf(Suite::class));

    it("returns specifications via getter", function(Specification $spec1, Specification $spec2) {
        $suite = new Suite([$spec1, $spec2]);
        expect($suite->getSpecifications())->toHaveCount(2);
    });

    it("runs a single specification", function(Specification $specification, Results $stubResults) {
        $specification->run()->toReturn($stubResults);

        $suite = new Suite([$specification]);
        $result = $suite->run();

        expect($result)->toBeAnInstanceOf(SuiteResult::class);
        expect($result->getResults())->toHaveCount(1);
    });

    it("runs multiple specifications", function(Specification $spec1, Specification $spec2, Results $results1, Results $results2) {
        $spec1->run()->toReturn($results1);
        $spec2->run()->toReturn($results2);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run();

        expect($result)->toBeAnInstanceOf(SuiteResult::class);
        expect($result->getResults())->toHaveCount(2);
    });

    it("stops on first failure when onFailure is set", function(Specification $spec1, Specification $spec2) {
        $failingResult = new SpecificationResult("failing", [
            new ExampleResult("fail", [], true)
        ]);

        $spec1->run()->toReturn($failingResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onFailure: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("runs all specs when no stop conditions even with failures", function(Specification $spec1, Specification $spec2) {
        $failingResult = new SpecificationResult("failing", [
            new ExampleResult("fail", [], true)
        ]);

        $spec1->run()->toReturn($failingResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run();

        expect($result->getResults())->toHaveCount(2);
    });

    it("runs specs in random order with seed", function(Specification $spec1, Specification $spec2, Specification $spec3) {
        $passingResult = new SpecificationResult("passing", []);

        $spec1->run()->toReturn($passingResult);
        $spec2->run()->toReturn($passingResult);
        $spec3->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2, $spec3]);
        $result = $suite->run(new StopConditions(), 42);

        expect($result->getResults())->toHaveCount(3);
    });

    it("stops on failure inside nested context", function(Specification $spec1, Specification $spec2) {
        $failMatch = \PhpSpec\Result\MatchResult::failed("a", "b", "fail", __FILE__, __LINE__);
        $failEx = new ExampleResult("fail", [$failMatch]);
        $ctx = new ContextResult("ctx", [$failEx]);
        $failingResult = new SpecificationResult("spec", [$ctx]);

        $spec1->run()->toReturn($failingResult);

        $passingResult = new SpecificationResult("ok", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onFailure: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("continues past passing spec before stopping on failure", function(Specification $spec1, Specification $spec2) {
        $passingResult = new SpecificationResult("passing", [
            new ExampleResult("ok", [\PhpSpec\Result\MatchResult::passed()])
        ]);
        $failingResult = new SpecificationResult("failing", [
            new ExampleResult("fail", [], true)
        ]);

        $spec1->run()->toReturn($passingResult);
        $spec2->run()->toReturn($failingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onFailure: true));

        expect($result->getResults())->toHaveCount(2);
    });

    it("stops on failed step with onFailure", function(Specification $spec1, Specification $spec2) {
        $failedStep = new StepResult("Given something", "failure");
        $scenario = new ScenarioResult("Scenario", [$failedStep]);
        $featureResult = new FeatureResult("Feature", [$scenario]);

        $spec1->run()->toReturn($featureResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onFailure: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on error with onFailure", function(Specification $spec1, Specification $spec2) {
        $errorExample = new ExampleResult("err", [], true);
        $errorExample->setError(new \PhpSpec\Specification\ExampleError("err", new \RuntimeException("err")));
        $errorResult = new SpecificationResult("erroring", [$errorExample]);

        $spec1->run()->toReturn($errorResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onFailure: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on warning with onWarning", function(Specification $spec1, Specification $spec2) {
        $warningExample = new ExampleResult("warn", [\PhpSpec\Result\MatchResult::passed()]);
        $warningExample->setWarnings([['severity' => E_WARNING, 'message' => 'oops', 'file' => __FILE__, 'line' => __LINE__]]);
        $warnResult = new SpecificationResult("warning", [$warningExample]);

        $spec1->run()->toReturn($warnResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onWarning: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on skipped with onSkipped", function(Specification $spec1, Specification $spec2) {
        $skippedExample = new ExampleResult("skip", [], false, false, true);
        $skipResult = new SpecificationResult("skipping", [$skippedExample]);

        $spec1->run()->toReturn($skipResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onSkipped: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on error with onError", function(Specification $spec1, Specification $spec2) {
        $errorExample = new ExampleResult("err", [], true);
        $errorResult = new SpecificationResult("erroring", [$errorExample]);

        $spec1->run()->toReturn($errorResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onError: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on deprecation with onDeprecation", function(Specification $spec1, Specification $spec2) {
        $depExample = new ExampleResult("dep", [\PhpSpec\Result\MatchResult::passed()]);
        $depExample->setDeprecations([['severity' => E_USER_DEPRECATED, 'message' => 'old API', 'file' => __FILE__, 'line' => __LINE__]]);
        $depResult = new SpecificationResult("deprecating", [$depExample]);

        $spec1->run()->toReturn($depResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onDeprecation: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on notice with onNotice", function(Specification $spec1, Specification $spec2) {
        $noticeExample = new ExampleResult("note", [\PhpSpec\Result\MatchResult::passed()]);
        $noticeExample->setNotices([['severity' => E_USER_NOTICE, 'message' => 'FYI', 'file' => __FILE__, 'line' => __LINE__]]);
        $noticeResult = new SpecificationResult("noticing", [$noticeExample]);

        $spec1->run()->toReturn($noticeResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(new StopConditions(onNotice: true));

        expect($result->getResults())->toHaveCount(1);
    });

    it("stops on all problems with fromProblems", function(Specification $spec1, Specification $spec2) {
        $warningExample = new ExampleResult("warn", [\PhpSpec\Result\MatchResult::passed()]);
        $warningExample->setWarnings([['severity' => E_WARNING, 'message' => 'oops', 'file' => __FILE__, 'line' => __LINE__]]);
        $warnResult = new SpecificationResult("warning", [$warningExample]);

        $spec1->run()->toReturn($warnResult);

        $passingResult = new SpecificationResult("passing", []);
        $spec2->run()->toReturn($passingResult);

        $suite = new Suite([$spec1, $spec2]);
        $result = $suite->run(StopConditions::fromProblems());

        expect($result->getResults())->toHaveCount(1);
    });
});
