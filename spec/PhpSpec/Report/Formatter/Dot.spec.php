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
        expect($text)->toContain("Deprecated:");
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

});
