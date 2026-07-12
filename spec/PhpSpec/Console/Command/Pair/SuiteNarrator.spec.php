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

});
