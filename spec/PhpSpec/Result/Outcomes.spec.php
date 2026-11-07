<?php

use PhpSpec\ProjectRoot;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\Kind;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\Outcomes;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\State;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\ExampleError;

describe(Outcomes::class, function () {

    this('outcomes', fn() => new Outcomes(ProjectRoot::at('/project')));

    this('walked', fn() => function (Outcomes $outcomes, SuiteResult $suite): array {
        $walked = [];
        $outcomes->walk($suite, function (Outcome $outcome, ExampleResult|ScenarioResult $leaf) use (&$walked): void {
            $walked[] = [$outcome, $leaf];
        });

        return $walked;
    });

    it('names an example by its describe and contexts, not by its spec file, and hands over the result it came from', function () {
        $adds = new ExampleResult('adds', [MatchResult::passed()]);
        $adds->declaredAt('/project/spec/Calc.spec.php', 7);
        $suite = new SuiteResult([new SpecificationResult('Calc', [new ContextResult('Calc', [new ContextResult('when empty', [$adds])])])]);

        [[$outcome, $leaf]] = ($this->walked)($this->outcomes, $suite);

        expect($outcome->kind)->toBe(Kind::Example);
        expect($outcome->name())->toBe('Calc > when empty > adds');
        expect((string) $outcome->writtenAt)->toBe('spec/Calc.spec.php:7');
        expect($leaf)->toBe($adds);
    });

    it('names a scenario by its feature, and writes it at its line in the feature file', function () {
        $failing = new StepResult('Then it subtracts', 'failure');
        $failing->setMatch(MatchResult::failed(1, 2, 'Expected 1 to be 2', '/project/features/steps/calc.php', 4));
        $suite = new SuiteResult([new FeatureResult('Calc', [new ScenarioResult('Subtracts', [$failing], 9)], '/project/features/calc.feature')]);

        [[$outcome]] = ($this->walked)($this->outcomes, $suite);

        expect([$outcome->kind, $outcome->name(), $outcome->state, $outcome->step])->toBe([Kind::Scenario, 'Calc > Subtracts', State::Failed, 'Then it subtracts']);
        expect((string) $outcome->writtenAt)->toBe('features/calc.feature:9');
        expect((string) $outcome->failedAt)->toBe('features/steps/calc.php:4');
    });

    it('names the result a broken context reports its error on by the context alone', function () {
        $carrier = new ExampleResult('Server', [], true);
        $carrier->setError(new ExampleError('would not stop', new \RuntimeException('would not stop')));
        $context = new ContextResult('Server', [new ExampleResult('answers', [MatchResult::passed()]), $carrier]);
        $context->setError($carrier->getError());

        $names = array_map(fn(array $walked) => $walked[0]->name(), ($this->walked)($this->outcomes, new SuiteResult([new SpecificationResult('Server', [$context])])));

        expect($names)->toBe(['Server > answers', 'Server']);
    });
});
