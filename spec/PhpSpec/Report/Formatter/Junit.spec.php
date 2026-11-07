<?php

use PhpSpec\ProjectRoot;
use PhpSpec\Report\Formatter\Junit;
use PhpSpec\Result\Outcomes;
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

    this('outcomes', fn() => new Outcomes(ProjectRoot::here()));

    it("formats passing results as XML", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output, $this->outcomes);

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

    it("writes in a failure what failed and where, as PHPUnit does", function() {
        $output = new BufferedOutput();
        $match = MatchResult::failed("a", "b", "Expected a to be b", __FILE__, 42);

        (new Junit($output, $this->outcomes))->format(new SuiteResult([new SpecificationResult("MySpec", [new ExampleResult("fails", [$match])])]));

        expect($output->fetch())->toContain("<failure message=\"Expected a to be b\">Expected a to be b\n\nspec/PhpSpec/Report/Formatter/Junit.spec.php:42</failure>");
    });

    it("formats failing results with failure element", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output, $this->outcomes);

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
        $formatter = new Junit($output, $this->outcomes);

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
        $formatter = new Junit($output, $this->outcomes);

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
        $formatter = new Junit($output, $this->outcomes);

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
            $formatter = new Junit($output, $this->outcomes);

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

            expect($text)->toContain('<testsuite name="User authentication" type="feature" tests="1"');
            expect($text)->toContain('<testcase name="Successful login" classname="User authentication"');
            expect($text)->not()->toContain('Given a user');
        });

        it('gives a scenario left pending or skipped the reason its deciding step gave', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output, $this->outcomes);

            $scenario = new ScenarioResult('Checkout', [
                new StepResult('When I pay', 'pending', 'Needs the payment gateway'),
                new StepResult('Then I see a receipt', 'skipped', 'No printer here'),
            ]);
            $formatter->format(new SuiteResult([new FeatureResult('Shop', [$scenario])]));

            $text = $output->fetch();
            expect($text)->toContain('<skipped message="Needs the payment gateway"/>');
            expect($text)->toContain('skipped="1"');
        });

        it('formats pending steps with skipped element', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output, $this->outcomes);

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

        it('counts a feature\'s scenarios as its tests, each judged as the run judges it', function () {
            $output = new BufferedOutput();
            $failed = new StepResult('Then it is refunded', 'failure');
            $failed->setError(new StepError('Expected 0 to be 5', new \RuntimeException('Expected 0 to be 5')));
            $scenario = new ScenarioResult('Checkout', [new StepResult('When I pay', 'passed'), $failed, new StepResult('Then I see a receipt', 'skipped')]);

            (new Junit($output, $this->outcomes))->format(new SuiteResult([new FeatureResult('Shop', [$scenario, new ScenarioResult('Browsing', [new StepResult('When I browse', 'passed')])])]));

            $text = $output->fetch();
            expect($text)->toMatch('/<testsuite name="Shop" type="feature" tests="2" failures="1" errors="0" skipped="0"/');
            expect($text)->not()->toContain('type="scenario"');
        });

        it('fails an undefined step, as it fails the run', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output, $this->outcomes);

            $steps = [
                new StepResult('Given an undefined step', 'undefined'),
            ];
            $scenario = new ScenarioResult('Scenario', $steps);
            $feature = new FeatureResult('Feature', [$scenario]);
            $suite = new SuiteResult([$feature]);

            $formatter->format($suite);
            $text = $output->fetch();

            expect($text)->toContain('<failure message="Undefined step: Given an undefined step"');
        });

        it('formats failed steps with failure element', function () {
            $output = new BufferedOutput();
            $formatter = new Junit($output, $this->outcomes);

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
            $formatter = new Junit($output, $this->outcomes);

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
        $formatter = new Junit($output, $this->outcomes);

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
        $formatter = new Junit($output, $this->outcomes);

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

    it("records how long each scenario and feature ran as time, and where each scenario is written", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output, $this->outcomes);

        $given = new StepResult('Given a basket', 'passed');
        $given->setDuration(0.25);
        $when = new StepResult('When I pay', 'passed');
        $when->setDuration(0.75);
        $feature = new FeatureResult('Checkout', [new ScenarioResult('Paying', [$given, $when], 3)], 'features/checkout.feature');

        $formatter->format(new SuiteResult([$feature]));
        $text = $output->fetch();
        expect($text)->toContain('<testcase name="Paying" classname="Checkout" file="features/checkout.feature" line="3" time="1.000000"');
        expect($text)->toContain('<testsuite name="Checkout" type="feature" file="features/checkout.feature" tests="1" failures="0" errors="0" skipped="0" time="1.000000"');
    });

    it("formats results with nested contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Junit($output, $this->outcomes);

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
