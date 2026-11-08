<?php

use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\LoadingNotes;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;

describe(LoadingNotes::class, function () {

    this('note', fn() => fn(string $message, bool $loading = true): array => ['severity' => E_USER_WARNING, 'message' => $message, 'file' => 'a.php', 'line' => 1] + ($loading ? ['loading' => true] : []));

    this('example', fn() => function (array ...$notes): ExampleResult {
        $example = new ExampleResult('warns', []);
        $example->raised($notes);

        return $example;
    });

    it('keeps the first report of what a file raised as it loaded, and drops it from every later one', function () {
        $notes = new LoadingNotes();
        $first = ($this->example)(($this->note)('loads'));
        $second = ($this->example)(($this->note)('loads'));

        $notes->keepFirstIn(new SpecificationResult('A', [new ContextResult('A', [$first])], 'a.spec.php'));
        $notes->keepFirstIn(new SpecificationResult('B', [new ContextResult('B', [$second])], 'b.spec.php'));

        expect($first->getWarnings())->toHaveCount(1);
        expect($second->getWarnings())->toBe([]);
    });

    it('finds it on a step of a feature as well', function () {
        $notes = new LoadingNotes();
        $step = new StepResult('Given a customer', 'passed');
        $later = new StepResult('Given a customer', 'passed');
        $step->raised([($this->note)('loads')]);
        $later->raised([($this->note)('loads')]);

        $notes->keepFirstIn(new FeatureResult('One', [new ScenarioResult('First', [$step], 2)], 'one.feature'));
        $notes->keepFirstIn(new FeatureResult('Two', [new ScenarioResult('Second', [$later], 2)], 'two.feature'));

        expect($step->getWarnings())->toHaveCount(1);
        expect($later->getWarnings())->toBe([]);
    });

    it('leaves alone what code raised when called, however often', function () {
        $notes = new LoadingNotes();
        $first = ($this->example)(($this->note)('called', false));
        $second = ($this->example)(($this->note)('called', false));

        $notes->keepFirstIn(new SpecificationResult('A', [$first], 'a.spec.php'));
        $notes->keepFirstIn(new SpecificationResult('B', [$second], 'b.spec.php'));

        expect($second->getWarnings())->toHaveCount(1);
    });
});
