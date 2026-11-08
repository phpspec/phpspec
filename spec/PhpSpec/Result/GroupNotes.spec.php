<?php

use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\GroupNotes;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;

describe(GroupNotes::class, function () {

    this('note', fn() => fn(string $message): array => ['severity' => E_USER_WARNING, 'message' => $message, 'file' => 'spec/App/Group.spec.php', 'line' => 1]);

    it('puts what set the group up on its first example, nested or not, and what tore it down on its last', function () {
        $first = new ExampleResult('first', []);
        $middle = new ExampleResult('middle', []);
        $last = new ExampleResult('last', []);

        (new GroupNotes([($this->note)('set up')], [($this->note)('torn down')]))->settleOn([new ContextResult('inner', [$first, $middle]), $last]);

        expect(array_column($first->getWarnings(), 'message'))->toBe(['set up']);
        expect($middle->getWarnings())->toBe([]);
        expect(array_column($last->getWarnings(), 'message'))->toBe(['torn down']);
    });

    it('puts a scenario group\'s notes on the first step that ran and on the last', function () {
        $opening = new StepResult('Given a basket', 'passed');
        $closing = new StepResult('Then it is paid', 'passed');

        (new GroupNotes([($this->note)('set up')], [($this->note)('torn down')]))->settleOn([new ScenarioResult('Paying', [$opening, $closing], 3)]);

        expect(array_column($opening->getWarnings(), 'message'))->toBe(['set up']);
        expect(array_column($closing->getWarnings(), 'message'))->toBe(['torn down']);
    });

    it('puts both on the one result a group holds, and nowhere when it holds none', function () {
        $only = new ExampleResult('only', []);

        (new GroupNotes([($this->note)('set up')], [($this->note)('torn down')]))->settleOn([$only]);
        (new GroupNotes([($this->note)('lost')], []))->settleOn([new ContextResult('empty', [])]);

        expect(array_column($only->getWarnings(), 'message'))->toBe(['set up', 'torn down']);
    });
});
