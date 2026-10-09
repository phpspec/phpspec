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
        expect($text)->toContain('type="RuntimeException"');
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

    it("marks a skipped example skipped too, each with the reason it gave", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $pending = new ExampleResult("is pending", [], isPending: true, reason: "Needs the rates API");
        $skipped = new ExampleResult("is skipped", [], isSkipped: true, reason: "No network here");
        $suite = new SuiteResult([new SpecificationResult("MySpec", [$pending, $skipped])]);

        $formatter->format($suite);
        $text = $output->fetch();

        expect($text)->toContain('<skipped message="Needs the rates API"/>');
        expect($text)->toContain('<skipped message="No network here"/>');
        expect($text)->toContain('skipped="2"');
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

        it('gives a pending or skipped step\'s skipped element the reason the step gave', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output);

            $scenario = new ScenarioResult('Checkout', [
                new StepResult('When I pay', 'pending', 'Needs the payment gateway'),
                new StepResult('Then I see a receipt', 'skipped', 'No printer here'),
            ]);
            $formatter->format(new SuiteResult([new FeatureResult('Shop', [$scenario])]));

            $text = $output->fetch();
            expect($text)->toContain('<skipped message="Needs the payment gateway"/>');
            expect($text)->toContain('<skipped message="No printer here"/>');
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

    it("names a testcase's class after the spec and the contexts around it, so one title in two contexts reads apart", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        // A spec file is titled after its name, and the describe inside it
        // after the class: a case is named by the describe and its contexts.
        $spec = new SpecificationResult("HappyHour", [
            new ContextResult("HappyHour", [
                new ExampleResult("opens", [MatchResult::passed()]),
                new ContextResult("at 5pm", [new ExampleResult("takes 20% off", [MatchResult::passed()])]),
                new ContextResult("at 9am", [new ContextResult("on a Monday", [new ExampleResult("takes 20% off", [MatchResult::passed()])])]),
            ]),
        ]);

        $formatter->format(new SuiteResult([$spec]));
        $text = $output->fetch();
        expect($text)->toContain('<testcase name="opens" classname="HappyHour &gt; HappyHour"');
        expect($text)->toContain('<testcase name="takes 20% off" classname="HappyHour &gt; HappyHour &gt; at 5pm"');
        expect($text)->toContain('<testcase name="takes 20% off" classname="HappyHour &gt; HappyHour &gt; at 9am &gt; on a Monday"');
    });

    it("records how long each example and the suite ran, in seconds, as time", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $quick = new ExampleResult("is quick", [MatchResult::passed()]);
        $quick->setDuration(0.0125);
        $slow = new ExampleResult("is slow", [MatchResult::passed()]);
        $slow->setDuration(0.5);

        $formatter->format(new SuiteResult([new SpecificationResult("Timed", [$quick, $slow])]));
        $text = $output->fetch();
        expect($text)->toContain('<testcase name="is quick" classname="Timed" time="0.012500"');
        expect($text)->toContain('<testcase name="is slow" classname="Timed" time="0.500000"');
        expect($text)->toContain('<testsuite name="Timed" tests="2" failures="0" errors="0" skipped="0" time="0.512500"');
    });

    it("records how long each step, scenario and feature ran as time", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output);

        $given = new StepResult('Given a basket', 'passed');
        $given->setDuration(0.25);
        $when = new StepResult('When I pay', 'passed');
        $when->setDuration(0.75);
        $feature = new FeatureResult('Checkout', [new ScenarioResult('Paying', [$given, $when], 3)], 'features/checkout.feature');

        $formatter->format(new SuiteResult([$feature]));
        $text = $output->fetch();
        expect($text)->toContain('<testcase name="Given a basket" classname="Checkout &gt; Paying" time="0.250000"');
        expect($text)->toContain('<testsuite name="Paying" type="scenario" line="3" time="1.000000"');
        expect($text)->toContain('<testsuite name="Checkout" type="feature" file="features/checkout.feature" time="1.000000"');
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
