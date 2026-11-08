<?php

use PhpSpec\Extensions\FormatterExtension;
use PhpSpec\Result\Kind;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\State;

describe(FormatterExtension::class, function () {

    this('formatter', fn() => new class extends FormatterExtension {
        public function getName(): string
        {
            return 'test';
        }

        public function formatPass(Outcome $outcome): string
        {
            return "PASS: {$outcome->name()}";
        }

        public function formatFail(Outcome $outcome): string
        {
            return "FAIL: {$outcome->name()} - {$outcome->message}";
        }
    });

    this('ended', fn() => fn(State $state, Kind $kind = Kind::Example): Outcome => new Outcome($kind, ['Calc', 'adds'], $state, 'bad', null, null, null, 0.0));

    it('is named', function () {
        expect($this->formatter->getName())->toBe('test');
    });

    it('formats an outcome by how it ended: a pass, a failure, an error, work left pending or skipped', function () {
        $formatted = array_map(fn(State $state) => $this->formatter->format(($this->ended)($state)), State::cases());

        expect(array_combine(array_map(fn(State $state) => $state->value, State::cases()), $formatted))->toBe([
            'passed' => 'PASS: Calc > adds',
            'failed' => 'FAIL: Calc > adds - bad',
            'errored' => 'FAIL: Calc > adds - bad',
            'pending' => 'PENDING: Calc > adds',
            'skipped' => 'PENDING: Calc > adds',
            'risky' => 'PASS: Calc > adds',
            'undefined' => 'FAIL: Calc > adds - bad',
        ]);
    });

    it('sums the outcomes up by kind and by how they ended', function () {
        $summary = $this->formatter->formatSummary([
            ($this->ended)(State::Passed),
            ($this->ended)(State::Failed),
            ($this->ended)(State::Passed, Kind::Scenario),
            ($this->ended)(State::Pending, Kind::Scenario),
            ($this->ended)(State::Undefined, Kind::Scenario),
        ]);

        expect($summary)->toBe('2 examples, 3 scenarios (2 passed, 1 failed, 1 pending, 1 undefined)');
        expect($this->formatter->formatSummary([]))->toBe('0 examples');
    });
});
