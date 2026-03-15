<?php

use PhpSpec\Report\Formatter\Junit;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Junit::class, function() {

    it("formats passing results as XML", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("passes", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('<?xml');
        expect($text)->toContain('<testsuites>');
        expect($text)->toContain('<testsuite');
        expect($text)->toContain('name="MySpec"');
        expect($text)->toContain('<testcase');
        expect($text)->toContain('name="passes"');
    });

    it("formats failing results with failure element", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $match = MatchResult::failed("a", "b", "Expected a to be b", __FILE__, __LINE__);
        $example = new ExampleResult("fails", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('<failure');
        expect($text)->toContain('Expected a to be b');
        expect($text)->toContain('failures="1"');
    });

    it("formats error results with error element", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $example = new ExampleResult("errors", [], true);
        $example->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('<error');
        expect($text)->toContain('boom');
        expect($text)->toContain('errors="1"');
    });

    it("formats pending results with skipped element", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $example = new ExampleResult("pending", [], false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('<skipped');
        expect($text)->toContain('skipped="1"');
    });

    context('feature formatting', function () {
        it('formats a feature with passing steps', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output);

            $steps = [
                new StepResult('Given a user', 'passed'),
                new StepResult('When they login', 'passed'),
                new StepResult('Then they see dashboard', 'passed'),
            ];
            $scenario = new ScenarioResult('Successful login', $steps);
            $feature = new FeatureResult('User authentication', [$scenario]);
            $suite = new SuiteResult([$feature]);

            $formatter->format($suite);
            $text = $output->fetch();

            expect($text)->toContain('type="feature"');
            expect($text)->toContain('name="User authentication"');
            expect($text)->toContain('type="scenario"');
            expect($text)->toContain('name="Successful login"');
            expect($text)->toContain('name="Given a user"');
            expect($text)->toContain('name="When they login"');
            expect($text)->toContain('name="Then they see dashboard"');
        });

        it('formats pending steps with skipped element', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output);

            $steps = [
                new StepResult('Given a pending step', 'pending'),
            ];
            $scenario = new ScenarioResult('Scenario', $steps);
            $feature = new FeatureResult('Feature', [$scenario]);
            $suite = new SuiteResult([$feature]);

            $formatter->format($suite);
            $text = $output->fetch();

            expect($text)->toContain('<skipped');
        });

        it('formats undefined steps with skipped element', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output);

            $steps = [
                new StepResult('Given an undefined step', 'undefined'),
            ];
            $scenario = new ScenarioResult('Scenario', $steps);
            $feature = new FeatureResult('Feature', [$scenario]);
            $suite = new SuiteResult([$feature]);

            $formatter->format($suite);
            $text = $output->fetch();

            expect($text)->toContain('<skipped');
        });

        it('formats failed steps with failure element', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output);

            $step = new StepResult('Then it fails', 'failure');
            $step->setError(new StepError('Assertion failed', new \RuntimeException('Assertion failed')));
            $scenario = new ScenarioResult('Scenario', [$step]);
            $feature = new FeatureResult('Feature', [$scenario]);
            $suite = new SuiteResult([$feature]);

            $formatter->format($suite);
            $text = $output->fetch();

            expect($text)->toContain('<failure');
            expect($text)->toContain('Assertion failed');
        });

        it('formats mixed specs and features', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output);

            $example = new ExampleResult('passes', [MatchResult::passed()]);
            $spec = new SpecificationResult('MySpec', [$example]);

            $step = new StepResult('Given something', 'passed');
            $scenario = new ScenarioResult('Scenario', [$step]);
            $feature = new FeatureResult('Feature', [$scenario]);

            $suite = new SuiteResult([$spec, $feature]);

            $formatter->format($suite);
            $text = $output->fetch();

            expect($text)->toContain('name="MySpec"');
            expect($text)->toContain('type="feature"');
            expect($text)->toContain('name="Feature"');
        });
    });

    it("formats results with nested contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("inner test", [$match]);
        $ctx = new ContextResult("my context", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('<testsuite');
        expect($text)->toContain('inner test');
    });

});
