<?php

use PhpSpec\Console\Command\Pair\PairRole;
use PhpSpec\Console\Command\Pair\SituationReport;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;

describe(SituationReport::class, function () {

    it('reports that the suite has not run when there is no outcome', function () {
        $report = SituationReport::fromOutcome(null, PairRole::HumanDrives)->render();

        expect($report)->toContain('SUITE: not run yet this session.');
    });

    it('names the failing example with its subject and error', function () {
        $summary = new SuiteSummary(
            'red',
            ['examples' => 2, 'passes' => 1, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers', 'error' => 'Expected 5 but got 3']],
        );
        $report = SituationReport::fromOutcome(new RunOutcome(null, $summary), PairRole::HumanDrives)->render();

        expect($report)->toContain('SUITE: red — 2 examples: 1 passed, 1 failed, 0 errored, 0 pending');
        expect($report)->toContain('FAILING:');
        expect($report)->toContain('App\\Calculator › adds numbers — Expected 5 but got 3');
    });

    it('lists pending examples without a dangling error separator', function () {
        $summary = new SuiteSummary(
            'green',
            ['examples' => 2, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 1],
            [],
            [['subject' => 'App\\Basket', 'example' => 'applies a discount code', 'error' => '']],
        );
        $report = SituationReport::fromOutcome(new RunOutcome(null, $summary), PairRole::HumanDrives)->render();

        expect($report)->toContain('PENDING:');
        expect($report)->toContain('App\\Basket › applies a discount code');
        expect($report)->not()->toContain('applies a discount code —');
    });

    it('reports a clean slate when there are no examples yet', function () {
        $summary = new SuiteSummary('green');
        $report = SituationReport::fromOutcome(new RunOutcome(null, $summary), PairRole::HumanDrives)->render();

        expect($report)->toContain('SUITE: green — no examples yet.');
        expect($report)->not()->toContain('FAILING:');
    });

    it('reminds the AI it is navigating when the human drives', function () {
        $report = SituationReport::fromOutcome(null, PairRole::HumanDrives)->render();

        expect($report)->toContain('NOTE: You are navigating');
        expect($report)->toContain('do not write files');
    });

    it('reminds the AI it is driving when the AI drives', function () {
        $report = SituationReport::fromOutcome(null, PairRole::AiDrives)->render();

        expect($report)->toContain('NOTE: You are driving');
        expect($report)->toContain('one-step plan');
    });

    it('renders without ANSI escape codes', function () {
        $summary = new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds', 'error' => 'boom']],
        );
        $report = SituationReport::fromOutcome(new RunOutcome(null, $summary), PairRole::AiDrives)->render();

        expect($report)->not()->toMatch('/\e\[/');
    });

});
