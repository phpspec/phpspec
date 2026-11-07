<?php

use PhpSpec\Extensions\ListenerBridge;
use PhpSpec\Extensions\ListenerExtension;
use PhpSpec\ProjectRoot;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\Outcomes;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;

describe(ListenerBridge::class, function () {

    this('listener', fn() => new class extends ListenerExtension {
        /** @var list<string> */
        public array $heard = [];

        public function beforeSuite(): void
        {
            $this->heard[] = 'began';
        }

        public function completed(Outcome $outcome): void
        {
            $this->heard[] = "{$outcome->state->value} {$outcome->name()}";
        }

        public function afterSuite(): void
        {
            $this->heard[] = 'ended';
        }
    });

    it('tells every listener the suite began, each outcome of every result it hears, and that the suite ended', function () {
        $other = clone $this->listener;
        $bridge = new ListenerBridge([$this->listener, $other], new Outcomes(ProjectRoot::at('/project')));

        $bridge->began();
        $bridge->heard(new SpecificationResult('Calc', [new ContextResult('Calc', [
            new ExampleResult('adds', [MatchResult::passed()]),
            new ExampleResult('waits', [], isPending: true),
        ])]));
        $bridge->heard(new FeatureResult('Calc', [new ScenarioResult('Subtracts', [new StepResult('Given a missing step', 'undefined')])], '/project/features/calc.feature'));
        $bridge->ended();

        expect($this->listener->heard)->toBe(['began', 'passed Calc > adds', 'pending Calc > waits', 'undefined Calc > Subtracts', 'ended']);
        expect($other->heard)->toBe($this->listener->heard);
    });
});
