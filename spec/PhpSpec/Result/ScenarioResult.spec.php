<?php

use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;

describe(ScenarioResult::class, function () {
    it('returns the scenario title', function () {
        $result = new ScenarioResult('My Scenario', []);
        expect($result->getTitle())->toBe('My Scenario');
    });

    it('returns child step results', function () {
        $step = new StepResult('Given something', 'passed');
        $result = new ScenarioResult('My Scenario', [$step]);

        expect($result->getResults())->toBe([$step]);
    });
});
