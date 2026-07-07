<?php

use PhpSpec\Report\Formatter\Html;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Html::class, function() {

    it("formats passing results as an HTML document", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("renders a passing example", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('<!DOCTYPE html>');
        expect($text)->toContain('MySpec');
        expect($text)->toContain('renders a passing example');
        expect($text)->toContain('class="example passed"');
        expect($text)->toContain('1 example');
    });

    it("formats failing results with their failure message", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $match = MatchResult::failed("a", "b", "Expected a to be b", __FILE__, __LINE__);
        $example = new ExampleResult("fails", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('class="example failed"');
        expect($text)->toContain('Expected a to be b');
        expect($text)->toContain('1 failure');
    });

    it("collapses the failure details under the failed example", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $line = __LINE__ + 1;
        $match = MatchResult::failed("a", "b", "Expected a to be b", __FILE__, $line);
        $example = new ExampleResult("fails", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);

        $formatter->format(new SuiteResult([$spec]));
        $text = $output->fetch();
        expect($text)->toContain('<details class="example failed">');
        expect($text)->toContain('<summary>fails</summary>');
        expect($text)->toContain('expected:');
        expect($text)->toContain('got:');
        expect($text)->toContain('at ' . __FILE__ . ':' . $line);
        expect($text)->toContain('class="snippet"');
        expect($text)->toContain('MatchResult::failed');
    });

    it("collapses the error message under a failed step", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $step = new StepResult("Then it fails", "failure");
        $step->setError(new StepError("Assertion failed badly", new \RuntimeException("boom")));
        $story = new FeatureResult("My feature", [
            new ScenarioResult("My scenario", [$step]),
        ], "features/my.feature");

        $formatter->format(new SuiteResult([$story]));
        $text = $output->fetch();
        expect($text)->toContain('<details class="example failure">');
        expect($text)->toContain('<summary>Then it fails</summary>');
        expect($text)->toContain('Assertion failed badly');
    });

    it("formats error results with the error message", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $example = new ExampleResult("errors", [], true);
        $example->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('class="example error"');
        expect($text)->toContain('boom');
    });

    it("marks pending and skipped examples", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $pending = new ExampleResult("not yet written", [], false, true);
        $skipped = new ExampleResult("not on this platform", [], false, false, true);
        $spec = new SpecificationResult("MySpec", [$pending, $skipped]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('class="example pending"');
        expect($text)->toContain('class="example skipped"');
    });

    it("opens groups containing failures and collapses passing ones", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $passing = new SpecificationResult("AllGood", [new ExampleResult("passes", [MatchResult::passed()])]);
        $failing = new SpecificationResult("Broken", [
            new ExampleResult("fails", [MatchResult::failed("a", "b", "nope", __FILE__, __LINE__)]),
        ]);
        $suite = new SuiteResult([$passing, $failing]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toMatch('/<details class="group passed">\s*<summary>/');
        expect($text)->toMatch('/<details class="group failed" open>\s*<summary>/');
        expect($text)->toContain('1 example · 1 failed');
    });

    it("shows tabs only when specs and stories are mixed", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $spec = new SpecificationResult("MySpec", [new ExampleResult("passes", [MatchResult::passed()])]);
        $story = new FeatureResult("My feature", [
            new ScenarioResult("My scenario", [new StepResult("Given a step", "passed")]),
        ], "features/my.feature");

        $formatter->format(new SuiteResult([$spec, $story]));
        $mixed = $output->fetch();
        expect($mixed)->toContain('role="tablist"');
        expect($mixed)->toContain('Specs');
        expect($mixed)->toContain('Stories');
        expect($mixed)->toContain('My scenario');

        $formatter->format(new SuiteResult([$spec]));
        $specsOnly = $output->fetch();
        expect($specsOnly)->not()->toContain('role="tablist"');
    });

    it("fills the pass-ratio meter proportionally", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $spec = new SpecificationResult("Halves", [
            new ExampleResult("passes", [MatchResult::passed()]),
            new ExampleResult("fails", [MatchResult::failed("a", "b", "nope", __FILE__, __LINE__)]),
        ]);

        $formatter->format(new SuiteResult([$spec]));
        expect($output->fetch())->toContain('width:50.0%');
    });

    it("renders nested contexts and escapes HTML in titles and messages", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $match = MatchResult::failed("a", "b", 'Expected <b> to be "safe"', __FILE__, __LINE__);
        $example = new ExampleResult('handles <input> titles', [$match]);
        $context = new ContextResult('when <nested>', [$example]);
        $spec = new SpecificationResult("MySpec", [$context]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('when &lt;nested&gt;');
        expect($text)->toContain('handles &lt;input&gt; titles');
        expect($text)->toContain('Expected &lt;b&gt; to be &quot;safe&quot;');
        expect($text)->not()->toContain('<input>');
    });
});
