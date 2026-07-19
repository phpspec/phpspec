<?php

use PhpSpec\Console\Command\Pair\PairRole;
use PhpSpec\Console\Command\Pair\SuiteNarrator;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;

describe(SuiteNarrator::class, function () {

    let('narrator', fn() => new SuiteNarrator());

    // The observation (red/green/pending) is drawn from suite state and is the
    // same whether or not an AI provider is configured.

    it('names the failing subject when the suite is red', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
        ));

        $text = implode("\n", $this->narrator->greeting($outcome, true));

        expect($text)->toContain('red');
        expect($text)->toContain('App\\Calculator');
        expect($text)->toContain('adds numbers');
    });

    it('names the nearest pending gap on a green suite', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 2, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 1],
            [],
            [['subject' => 'App\\Basket', 'example' => 'applies a discount code']],
        ));

        $text = implode("\n", $this->narrator->greeting($outcome, true));

        expect($text)->toContain('applies a discount code');
    });

    it('offers a clean observation on a green suite with no gaps', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        ));

        $text = implode("\n", $this->narrator->greeting($outcome, true));

        expect($text)->toContain('Green');
    });

    // AI available: natural language is on the table.

    it('invites plain English and a first sentence for an empty project when AI is on', function () {
        $text = implode("\n", $this->narrator->greeting(null, true));

        expect($text)->toContain('building');
        expect($text)->toContain('plain English works');
    });

    // AI not configured: steer to the deterministic commands, never promise plain English.

    it('steers an empty project to describe when AI is off', function () {
        $text = implode("\n", $this->narrator->greeting(null, false));

        expect($text)->toContain('describe');
        expect($text)->not()->toContain('plain English');
    });

    it('offers the deterministic commands in the footer when AI is off', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        ));

        $text = implode("\n", $this->narrator->greeting($outcome, false));

        expect($text)->toContain('describe');
        expect($text)->toContain('exemplify');
        expect($text)->toContain('run');
        expect($text)->toContain('next');
        expect($text)->toContain('/swap');
        expect($text)->not()->toContain('plain English');
    });

    it('promises plain English in the footer when AI is on', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        ));

        $text = implode("\n", $this->narrator->greeting($outcome, true));

        expect($text)->toContain('plain English works');
        expect($text)->toContain('/swap');
        expect($text)->toContain('/help');
    });

    // next() — the role-dependent next step, drawn from real suite state.

    it('coaches to run and names the failure when the suite is red, never re-describing', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 0, 'errors' => 1, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives);

        expect($next['action'])->toBe('run');
        $text = implode("\n", $next['lines']);
        expect($text)->toContain('App\\Calculator');
        expect($text)->toContain('run');
        expect($text)->not()->toContain('describe');
    });

    it('points at the nearest pending gap on a green suite', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 2, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 1],
            [],
            [['subject' => 'App\\Ledger', 'example' => 'reconciles entries']],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives);

        expect($next['action'])->toBe('exemplify');
        expect(implode("\n", $next['lines']))->toContain('reconciles entries');
    });

    it('invites a first spec for an empty project', function () {
        $next = $this->narrator->next(null, PairRole::HumanDrives);

        expect($next['action'])->toBe('describe');
        expect(implode("\n", $next['lines']))->toContain('describe');
    });

    it('speaks as the driver when the AI holds the keyboard', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 0, 'errors' => 1, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
        ));

        $next = $this->narrator->next($outcome, PairRole::AiDrives);

        expect($next['action'])->toBe('run');
        expect(implode("\n", $next['lines']))->toContain("I'll");
    });

    // next() — feature-first, outside-in. Features lead when present.

    it('advises writing the steps when a feature still has undefined steps', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
            [['path' => 'features/adding.feature', 'status' => 'todo', 'undefined' => 3]],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives);

        $text = implode("\n", $next['lines']);
        expect($text)->toContain('adding.feature');
        expect($text)->toContain('steps');
    });

    it('drops into the inner cycle, naming the failing example, when a scenario is red', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 0, 'errors' => 1, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 2, 'stepFailures' => 1, 'undefined' => 0],
            [['path' => 'features/adding.feature', 'status' => 'red', 'undefined' => 0]],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives);

        expect($next['action'])->toBe('run');
        expect(implode("\n", $next['lines']))->toContain('App\\Calculator');
    });

    it('advises running to drive out the behaviour a red scenario needs, even with no failing example yet', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'red',
            ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 2, 'stepFailures' => 1, 'undefined' => 0],
            [['path' => 'features/checkout.feature', 'status' => 'red', 'undefined' => 0]],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives);

        expect($next['action'])->toBe('run');
        expect(implode("\n", $next['lines']))->toContain('checkout.feature');
    });

    it('offers refactor, a new scenario, or a new feature when the features are green', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 2, 'passes' => 2, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 2, 'steps' => 6, 'stepFailures' => 0, 'undefined' => 0],
            [['path' => 'features/adding.feature', 'status' => 'green', 'undefined' => 0]],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives, 'features/adding.feature', 'src/App/Calculator.php');

        $text = implode("\n", $next['lines']);
        expect($text)->toContain('refactor');
        expect($text)->toContain('Calculator.php');
        expect($text)->toContain('new scenario');
        expect($text)->toContain('adding.feature');
        expect($text)->toContain('new feature');
    });

    it('falls back to the spec flow when there are no features', function () {
        $outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        ));

        $next = $this->narrator->next($outcome, PairRole::HumanDrives);

        expect($next['action'])->toBe('observe');
    });

});
