<?php

use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\State;
use PhpSpec\Result\StepResult;
use PhpSpec\StoryBDD\StepError;

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

    it('is decided by its first step that fails the run, else by its first step left out on purpose, else it passed', function () {
        $failing = new StepResult('Then it adds', 'failure');
        $failing->setError(new StepError('Expected 1 to be 2', new \RuntimeException('Expected 1 to be 2')));
        $failing->setMatch(MatchResult::failed(1, 2, 'Expected 1 to be 2', __FILE__, $line = __LINE__));
        $hookBroke = new StepResult('Then it shows', 'error');
        $hookBroke->setError(StepError::fromReport('boom (in afterScenario)', 'RuntimeException', '/project/features/steps/hooks.php', 9));
        $verdict = fn(ScenarioResult $scenario) => [$scenario->state(), $scenario->message(), $scenario->reason()];

        expect($verdict(new ScenarioResult('Empty', [])))->toBe([State::Passed, null, null]);
        expect($verdict(new ScenarioResult('Passes', [new StepResult('Given a', 'passed'), new StepResult('Then b', 'passed')])))->toBe([State::Passed, null, null]);
        expect($verdict(new ScenarioResult('Waits, then lacks a step', [new StepResult('Given a', 'pending', 'not yet'), new StepResult('Then b', 'undefined')])))->toBe([State::Undefined, 'Undefined step: Then b', null]);
        expect($verdict(new ScenarioResult('Fails, then its teardown breaks', [new StepResult('Given a', 'passed'), $failing, new StepResult('When c', 'skipped'), $hookBroke])))->toBe([State::Failed, 'Expected 1 to be 2', null]);
        expect($verdict(new ScenarioResult('Waits, then its teardown breaks', [new StepResult('Given a', 'pending', 'later'), new StepResult('When c', 'skipped'), $hookBroke])))->toBe([State::Errored, 'boom (in afterScenario)', null]);
        expect($verdict(new ScenarioResult('Skipped by a hook', [new StepResult('Given a', 'skipped', 'no db'), new StepResult('Then b', 'skipped')])))->toBe([State::Skipped, null, 'no db']);
        expect($verdict(new ScenarioResult('Waits', [new StepResult('Given a', 'pending', 'not yet'), new StepResult('Then b', 'skipped')])))->toBe([State::Pending, null, 'not yet']);
    });

    it('went wrong where the step that decided it did, and took as long as its steps', function () {
        $failing = new StepResult('Then it adds', 'failure');
        $failing->setMatch(MatchResult::failed(1, 2, 'Expected 1 to be 2', __FILE__, $line = __LINE__));
        $failing->setDuration(0.25);
        $passing = new StepResult('Given a', 'passed');
        $passing->setDuration(0.5);
        $scenario = new ScenarioResult('Fails', [$passing, $failing]);

        expect($scenario->decidingStep())->toBe($failing);
        expect((string) $scenario->failedAt())->toBe(__FILE__ . ':' . $line);
        expect($scenario->getDuration())->toBe(0.75);
        expect((new ScenarioResult('Passes', [$passing]))->failedAt())->toBeNull();
    });
});
