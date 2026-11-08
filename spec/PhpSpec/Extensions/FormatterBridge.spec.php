<?php

use PhpSpec\Extensions\FormatterBridge;
use PhpSpec\Extensions\FormatterExtension;
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
use PhpSpec\Result\SuiteResult;
use PhpSpec\StoryBDD\StepError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(FormatterBridge::class, function () {

    this('extension', fn() => new class extends FormatterExtension {
        public function getName(): string
        {
            return 'test';
        }

        public function formatPass(Outcome $outcome): string
        {
            return "OK: {$outcome->name()}";
        }

        public function formatFail(Outcome $outcome): string
        {
            return "FAIL: {$outcome->name()} - {$outcome->message}";
        }
    });

    this('outcomes', fn() => new Outcomes(ProjectRoot::at('/project')));

    it('formats each example under its describe and contexts, by how it ended', function () {
        $output = new BufferedOutput();
        $suite = new SuiteResult([new SpecificationResult('Calc', [new ContextResult('Calc', [
            new ExampleResult('adds', [MatchResult::passed()]),
            new ExampleResult('subtracts', [MatchResult::failed(1, 2, 'Expected 1 to be 2', __FILE__, __LINE__)]),
        ])])]);

        (new FormatterBridge($this->extension, $output, $this->outcomes))->format($suite);
        $text = $output->fetch();

        expect($text)->toContain('OK: Calc > adds');
        expect($text)->toContain('FAIL: Calc > subtracts - Expected 1 to be 2');
    });

    it('formats each scenario of a feature, under the feature', function () {
        $output = new BufferedOutput();
        $failing = new StepResult('Then it subtracts', 'failure');
        $failing->setError(new StepError('Expected 1 to be 2', new \RuntimeException('Expected 1 to be 2')));
        $suite = new SuiteResult([new FeatureResult('Calc', [
            new ScenarioResult('Adds', [new StepResult('Then it adds', 'passed')]),
            new ScenarioResult('Subtracts', [$failing]),
        ], '/project/features/calc.feature')]);

        (new FormatterBridge($this->extension, $output, $this->outcomes))->format($suite);
        $text = $output->fetch();

        expect($text)->toContain('OK: Calc > Adds');
        expect($text)->toContain('FAIL: Calc > Subtracts - Expected 1 to be 2');
    });

    it('sums up what it formatted, then says how long the run took', function () {
        $output = new BufferedOutput();
        $suite = new SuiteResult([
            new SpecificationResult('Calc', [new ExampleResult('adds', [MatchResult::passed()])]),
            new FeatureResult('Calc', [new ScenarioResult('Waits', [new StepResult('Given a step', 'undefined')])], '/project/features/calc.feature'),
        ]);
        $suite->setDuration(1.5);

        (new FormatterBridge($this->extension, $output, $this->outcomes))->format($suite);
        $text = $output->fetch();

        expect($text)->toContain('1 example, 1 scenario (1 passed, 1 undefined)');
        expect($text)->toContain('Finished in 1.5');
    });
});
