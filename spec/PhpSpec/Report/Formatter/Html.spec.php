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
        expect($text)->toContain('<p>1 spec, 1 example (1 failed)</p>');
    });

    it("words the footer as the console summary does, every outcome counted once", function() {
        $output = new BufferedOutput();
        $errored = new ExampleResult("breaks", [], true);
        $errored->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $risky = new ExampleResult("checks nothing", []);
        $risky->markRisky();
        $spec = new SpecificationResult("MySpec", [
            new ExampleResult("works", [MatchResult::passed()]),
            $risky,
            new ExampleResult("fails", [MatchResult::failed(1, 2, "Expected 1 to be 2", __FILE__, __LINE__)]),
            $errored,
            new ExampleResult("waits", [], isPending: true),
            new ExampleResult("skips", [], isSkipped: true),
        ]);

        (new Html($output))->format(new SuiteResult([$spec]));

        expect($output->fetch())->toContain('<p>1 spec, 6 examples (1 passed, 1 risky, 1 failed, 1 errored, 1 pending, 1 skipped)</p>');
    });

    it("counts the stories on a line of their own, notes included, as the console does", function () {
        $output = new BufferedOutput();
        $step = new StepResult("Given the old API", "passed");
        $step->raised([['severity' => E_USER_WARNING, 'message' => 'the cache is cold', 'file' => '/proj/features/steps/steps.php', 'line' => 3]]);

        (new Html($output))->format(new SuiteResult([new FeatureResult("Legacy", [new ScenarioResult("Old API", [$step])])]));

        expect($output->fetch())->toContain('<p>1 feature, 1 scenario, 1 step (1 passed, 1 warning)</p>');
    });

    it("counts the notes in the header, and on the bar of the group that raised them", function () {
        $output = new BufferedOutput();
        $example = new ExampleResult("totals the entries", [MatchResult::passed()]);
        $example->raised([
            ['severity' => E_USER_WARNING, 'message' => 'totals are rounded', 'file' => '/proj/src/App/Ledger.php', 'line' => 9],
            ['severity' => E_USER_DEPRECATED, 'message' => 'sum() is deprecated', 'file' => '/proj/src/App/Ledger.php', 'line' => 10],
        ]);

        (new Html($output))->format(new SuiteResult([new SpecificationResult("Ledger", [$example])]));
        $text = $output->fetch();

        expect($text)->toContain('<p class="meta">1 example · 1 warning · 1 deprecation</p>');
        expect($text)->toContain('<span class="count">1 example · 1 warning · 1 deprecation</span>');
    });

    it("unfolds a passing example that raised a note to each note, with its kind, its text and the line that raised it", function () {
        $output = new BufferedOutput();
        $example = new ExampleResult("totals the entries", [MatchResult::passed()]);
        $example->raised([['severity' => E_USER_WARNING, 'message' => 'totals are <rounded>', 'file' => '/proj/src/App/Ledger.php', 'line' => 9]]);

        (new Html($output))->format(new SuiteResult([new SpecificationResult("Ledger", [$example])]));
        $text = $output->fetch();

        expect($text)->toContain('<details class="example passed">');
        expect($text)->toContain('<summary>totals the entries <span class="reason">1 warning</span></summary>');
        expect($text)->toContain('<li class="note warning">⚠ totals are &lt;rounded&gt; <span class="where">at /proj/src/App/Ledger.php:9</span></li>');
    });

    it("unfolds a step that raised a note, passing or not, and keeps a failure's message beside it", function () {
        $output = new BufferedOutput();
        $noted = new StepResult("Given the old API", "passed");
        $noted->raised([['severity' => E_USER_NOTICE, 'message' => 'the clock is local', 'file' => '/proj/features/steps/steps.php', 'line' => 5]]);
        $broken = new StepResult("Then it fails", "failure");
        $broken->setError(new StepError("Expected 1 to be 2", new \RuntimeException("Expected 1 to be 2")));
        $broken->raised([['severity' => E_USER_DEPRECATED, 'message' => 'check() is deprecated', 'file' => '/proj/features/steps/steps.php', 'line' => 8]]);

        (new Html($output))->format(new SuiteResult([new FeatureResult("Legacy", [new ScenarioResult("Old API", [$noted, $broken])])]));
        $text = $output->fetch();

        expect($text)->toContain('<summary>Given the old API <span class="reason">1 notice</span></summary>');
        expect($text)->toContain('<li class="note notice">ℹ the clock is local <span class="where">at /proj/features/steps/steps.php:5</span></li>');
        expect($text)->toContain('Expected 1 to be 2');
        expect($text)->toContain('<li class="note deprecation">⛔ check() is deprecated <span class="where">at /proj/features/steps/steps.php:8</span></li>');
    });

    it("counts an errored step among the failed in the header", function() {
        $output = new BufferedOutput();
        $step = new StepResult("Given a broken fixture", "error");
        $step->setError(new StepError("fixture down", new \RuntimeException("fixture down")));

        (new Html($output))->format(new SuiteResult([new FeatureResult("Story", [new ScenarioResult("Breaks", [$step])])]));

        expect($output->fetch())->toContain('<p class="meta">1 step · 1 failed</p>');
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
        expect($text)->toContain('<dt>expected:</dt><dd>&quot;b&quot;</dd><dt>got:</dt><dd>&quot;a&quot;</dd>');
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

    it("marks a risky example and says it checked nothing", function() {
        $output = new BufferedOutput();
        $risky = new ExampleResult("calls the code", []);
        $risky->markRisky();
        (new Html($output))->format(new SuiteResult([new SpecificationResult("MySpec", [$risky])]));

        expect($output->fetch())->toContain('<li class="example risky">calls the code <span class="reason">no expectation</span></li>');
    });

    it("shows the reason a pending or skipped example gave beside its title", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $pending = new ExampleResult("fetches the rates", [], isPending: true, reason: "Needs the <rates> API");
        $skipped = new ExampleResult("posts the order", [], isSkipped: true, reason: "No network here");
        $formatter->format(new SuiteResult([new SpecificationResult("MySpec", [$pending, $skipped])]));

        $text = $output->fetch();
        expect($text)->toContain('<li class="example pending">fetches the rates <span class="reason">Needs the &lt;rates&gt; API</span></li>');
        expect($text)->toContain('<li class="example skipped">posts the order <span class="reason">No network here</span></li>');
    });

    it("shows the reason a pending or skipped step gave beside its title", function() {
        $output = new BufferedOutput();
        $formatter = new Html($output);

        $scenario = new ScenarioResult("Checkout", [
            new StepResult("When I pay", "pending", "Needs the <payment> gateway"),
            new StepResult("Then I see a receipt", "skipped", "No printer here"),
        ]);
        $formatter->format(new SuiteResult([new FeatureResult("Shop", [$scenario])]));

        $text = $output->fetch();
        expect($text)->toContain('<li class="example pending">When I pay <span class="reason">Needs the &lt;payment&gt; gateway</span></li>');
        expect($text)->toContain('<li class="example skipped">Then I see a receipt <span class="reason">No printer here</span></li>');
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
