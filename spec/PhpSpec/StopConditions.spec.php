<?php

use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\StopConditions;

describe(StopConditions::class, function () {

    it("is met by an errored example when stopping on errors or on failures", function () {
        $errored = new ExampleResult('breaks', [], true);

        expect((new StopConditions(onError: true))->metBy($errored))->toBeTrue();
        expect((new StopConditions(onFailure: true))->metBy($errored))->toBeTrue();
        expect((new StopConditions(onWarning: true))->metBy($errored))->toBeFalse();
    });

    it("is not met by a passing example, whatever it stops on", function () {
        expect(StopConditions::fromProblems()->metBy(new ExampleResult('passes', [MatchResult::passed()])))->toBeFalse();
    });

    it("looks for a matching example anywhere under a spec result", function () {
        $failed = new ExampleResult('fails', [MatchResult::failed('a', 'b', 'Expected a to be b', __FILE__, __LINE__)]);
        $spec = new SpecificationResult('Spec', [new ContextResult('nested', [$failed])]);

        expect((new StopConditions(onFailure: true))->metBy($spec))->toBeTrue();
        expect((new StopConditions(onError: true))->metBy($spec))->toBeFalse();
    });

    it("is met by a skipped example only when stopping on skipped", function () {
        $skipped = new ExampleResult('skips', [], false, false, true);

        expect((new StopConditions(onSkipped: true))->metBy($skipped))->toBeTrue();
        expect((new StopConditions(onFailure: true))->metBy($skipped))->toBeFalse();
    });

    it("is met by a failed step under a feature when stopping on failures, never by a passing one", function () {
        $feature = fn(string $state) => new FeatureResult('Feature', [
            new ScenarioResult('Scenario', [new StepResult('Then it runs', $state)]),
        ]);
        $stop = new StopConditions(onFailure: true);

        expect($stop->metBy($feature('failure')))->toBeTrue();
        expect($stop->metBy($feature('passed')))->toBeFalse();
    });

    it("is met by a step that threw when stopping on failures or on errors", function () {
        $threw = new StepResult('Given a step', 'error');

        expect((new StopConditions(onFailure: true))->metBy($threw))->toBeTrue();
        expect((new StopConditions(onError: true))->metBy($threw))->toBeTrue();
        expect((new StopConditions(onSkipped: true))->metBy($threw))->toBeFalse();
    });

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
