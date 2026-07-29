<?php

use PhpSpec\Report\Formatter\Dot;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Dot::class, function() {

    it("formats passing results as dots", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("passes", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain(".");
        expect($text)->toContain("1 spec");
        expect($text)->toContain("1 example");
        expect($text)->toContain("1 passes");
    });

    it("formats failing results with F", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::failed("a", "b", "Expected a to be b", __FILE__, __LINE__);
        $example = new ExampleResult("fails", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("F");
        expect($text)->toContain("1 failures");
        expect($text)->toContain("Expected a to be b");
    });

    it("formats error results with E", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $example = new ExampleResult("errors", [], true);
        $example->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("E");
        expect($text)->toContain("1 errors");
        expect($text)->toContain("boom");
    });

    it("formats pending results with P", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $example = new ExampleResult("pending", [], false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("P");
        expect($text)->toContain("1 pending");
    });

    it("formats results with nested contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("inner", [$match]);
        $ctx = new ContextResult("ctx", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain(".");
    });

    it("formats results with warnings", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("warns", [$match]);
        $example->setWarnings([
            ['severity' => E_WARNING, 'message' => 'test warning', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("test warning");
        expect($text)->toContain("1 warning");
    });

    it("breaks into equal-length lines with right-aligned subtotals", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        // Use enough examples to guarantee multiple full lines on any terminal
        $total = 1000;
        $examples = [];
        for ($i = 0; $i < $total; $i++) {
            $examples[] = new ExampleResult("ex$i", [MatchResult::passed()]);
        }
        $spec = new SpecificationResult("MySpec", $examples);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();

        $lines = explode("\n", $text);
        // Match progress lines: lines ending with a right-aligned number
        $dotLines = array_values(array_filter($lines, fn($l) => preg_match('/\s+\d+\s*$/', $l)));
        // Must have multiple lines
        expect(count($dotLines))->toBeGreaterThan(2);
        // All full lines (except last) have the same number of dots
        $firstCount = substr_count($dotLines[0], '.');
        expect($firstCount)->toBeGreaterThan(10);
        for ($i = 1; $i < count($dotLines) - 1; $i++) {
            expect(substr_count($dotLines[$i], '.'))->toBe($firstCount);
        }
        // Last partial line is padded to same total length (number aligns)
        expect(strlen($dotLines[count($dotLines) - 1]))->toBe(strlen($dotLines[0]));
        // Final subtotal shows total
        expect($text)->toContain('1000');
    });

    it("prints subtotal on last partial line", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $examples = [];
        for ($i = 0; $i < 5; $i++) {
            $examples[] = new ExampleResult("ex$i", [MatchResult::passed()]);
        }
        $spec = new SpecificationResult("MySpec", $examples);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();

        // 5 dots won't fill a line, subtotal appears right-aligned at end
        expect($text)->toMatch('/\s+5\r?$/m');
    });

    it("formats results with deprecations", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("deprecated call", [$match]);
        $example->setDeprecations([
            ['severity' => E_USER_DEPRECATED, 'message' => 'Function foo() is deprecated', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Deprecations:");
        expect($text)->toContain("Function foo() is deprecated");
        expect($text)->toContain("1 deprecation");
    });

    it("formats results with notices", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("notice fired", [$match]);
        $example->setNotices([
            ['severity' => E_NOTICE, 'message' => 'Undefined variable $x', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Notice:");
        expect($text)->toContain('Undefined variable $x');
        expect($text)->toContain("1 notice");
    });

    it("formats deprecations nested in contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("nested depr", [$match]);
        $example->setDeprecations([
            ['severity' => E_USER_DEPRECATED, 'message' => 'Nested deprecation', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $ctx = new ContextResult("ctx", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Nested deprecation");
    });

    it("formats skipped results with S", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $example = new ExampleResult("skipped", [], false, false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("S");
        expect($text)->toContain("1 skipped");
    });

    it("formats step results", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $passing = new StepResult("Given something", "passed");
        $pending = new StepResult("When pending", "pending");
        $skipped = new StepResult("Then skipped", "skipped");
        $scenario = new ScenarioResult("basic", [$passing, $pending, $skipped]);
        $feature = new FeatureResult("My Feature", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain(".");
        expect($text)->toContain("P");
        expect($text)->toContain("*");
        expect($text)->toContain("1 feature");
        expect($text)->toContain("1 scenario");
        expect($text)->toContain("3 steps");
    });

    it("formats failed step result with F", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $failed = new StepResult("Then fails", "failure");
        $failed->setError(new StepError("Then fails", new \RuntimeException("step boom"), "steps.php", 10));
        $scenario = new ScenarioResult("bad", [$failed]);
        $feature = new FeatureResult("Failing", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("F");
        expect($text)->toContain("Then fails");
        expect($text)->toContain("1 failed");
    });

    it("shows duration when available", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("timed", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);
        $suite->setDuration(0.5);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Finished in");
    });

    it("does not show duration when zero", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $spec = new SpecificationResult("MySpec", [new ExampleResult("test", [MatchResult::passed()])]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->not()->toContain("Finished in");
    });

    it("shows multiple spec count", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $spec1 = new SpecificationResult("Spec1", [new ExampleResult("test1", [MatchResult::passed()])]);
        $spec2 = new SpecificationResult("Spec2", [new ExampleResult("test2", [MatchResult::passed()])]);
        $suite = new SuiteResult([$spec1, $spec2]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("2 specs");
        expect($text)->toContain("2 examples");
    });

    it("formats undefined step result", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $step = new StepResult("When unknown", "undefined");
        $scenario = new ScenarioResult("scenario", [$step]);
        $feature = new FeatureResult("Feature", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("P");
        expect($text)->toContain("undefined");
    });

    it("formats feature with passing and failing steps", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $passing = new StepResult("Given ok", "passed");
        $failing = new StepResult("Then fail", "failure");
        $failing->setError(new StepError("Then fail", new \RuntimeException("step fail"), "steps.php", 5));
        $scenario = new ScenarioResult("mixed", [$passing, $failing]);
        $feature = new FeatureResult("Mixed Feature", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("1 passed");
        expect($text)->toContain("1 failed");
    });

    it("shows no feature line when no features present", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $spec = new SpecificationResult("Spec", [new ExampleResult("test", [MatchResult::passed()])]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->not()->toContain("feature");
    });

    it("formats notices nested in contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $example = new ExampleResult("nested notice", [MatchResult::passed()]);
        $example->setNotices([
            ['severity' => E_NOTICE, 'message' => 'Nested notice msg', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $ctx = new ContextResult("ctx", [$example]);
        $spec = new SpecificationResult("Spec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Nested notice msg");
    });

    it("formats warnings nested in contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $example = new ExampleResult("nested warn", [MatchResult::passed()]);
        $example->setWarnings([
            ['severity' => E_WARNING, 'message' => 'Nested warning', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $ctx = new ContextResult("ctx", [$example]);
        $spec = new SpecificationResult("Spec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Nested warning");
    });

    it("formats multiple errors", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $ex1 = new ExampleResult("err1", [], true);
        $ex1->setError(new ExampleError("error one", new \RuntimeException("error one")));
        $ex2 = new ExampleResult("err2", [], true);
        $ex2->setError(new ExampleError("error two", new \RuntimeException("error two")));
        $spec = new SpecificationResult("Spec", [$ex1, $ex2]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Errors:");
        expect($text)->toContain("Spec > err1");
        expect($text)->toContain("Spec > err2");
        expect($text)->toContain("2 errors");
    });

    it("tells the same detailed story at the bottom as the pretty formatter", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $failing = new ExampleResult("fails", [
            MatchResult::failed("the haystack text", "the needle", "irrelevant", __FILE__, __LINE__, null, "toContain", false),
        ]);
        $skipped = new ExampleResult("skipped one", [], false, false, true);
        $spec = new SpecificationResult("MySpec", [$failing, $skipped]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Failures:");
        expect($text)->toContain("MySpec > fails");
        expect($text)->toContain('expected: "the haystack text"');
        expect($text)->toContain('to contain: "the needle"');
        expect($text)->toContain("Skipped:");
        expect($text)->toContain("MySpec > skipped one");
    });

    it("formats suite with no examples", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $spec = new SpecificationResult("Empty", []);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("1 spec");
        expect($text)->not()->toContain("example");
    });

    it("formats failures with error messages from nested results", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $fail = MatchResult::failed("a", "b", "Nested failure msg", __FILE__, __LINE__);
        $example = new ExampleResult("nested fail", [$fail]);
        $ctx = new ContextResult("ctx", [$example]);
        $spec = new SpecificationResult("Spec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Nested failure msg");
    });

    it("formats step errors from nested contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $step = new StepResult("Given step error", "failure");
        $step->setError(new StepError("Given step error", new \RuntimeException("step err"), "steps.php", 5));
        $scenario = new ScenarioResult("scen", [$step]);
        $feature = new FeatureResult("Feat", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("step err");
    });

    it("formats multiple features and scenarios", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $step1 = new StepResult("Given a", "passed");
        $step2 = new StepResult("Given b", "passed");
        $scen1 = new ScenarioResult("Scen1", [$step1]);
        $scen2 = new ScenarioResult("Scen2", [$step2]);
        $feat = new FeatureResult("Feat", [$scen1, $scen2]);
        $suite = new SuiteResult([$feat]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("1 feature");
        expect($text)->toContain("2 scenarios");
    });

    it("wraps step lines when many steps", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $steps = [];
        for ($i = 0; $i < 200; $i++) {
            $steps[] = new StepResult("Step $i", "passed");
        }
        $scenario = new ScenarioResult("many steps", $steps);
        $feature = new FeatureResult("Big Feature", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("200 steps");
    });

    it("formats multiple deprecations", function() {
        $output = new BufferedOutput();
        $formatter = new Dot($output);

        $example = new ExampleResult("multi depr", [MatchResult::passed()]);
        $example->setDeprecations([
            ['severity' => E_DEPRECATED, 'message' => 'depr1', 'file' => __FILE__, 'line' => __LINE__],
            ['severity' => E_DEPRECATED, 'message' => 'depr2', 'file' => __FILE__, 'line' => __LINE__],
        ]);
        $spec = new SpecificationResult("Spec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("1 deprecation");
    });

});
