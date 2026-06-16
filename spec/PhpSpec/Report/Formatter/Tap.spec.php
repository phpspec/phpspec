<?php

use PhpSpec\Report\Formatter\Tap;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Tap::class, function() {

    it("formats passing results", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("passes", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("TAP version 13");
        expect($text)->toContain("1..1");
        expect($text)->toContain("ok 1 - passes");
    });

    it("formats failing results", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output);

        $match = MatchResult::failed("a", "b", "Expected a to be b", __FILE__, __LINE__);
        $example = new ExampleResult("fails", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("not ok 1 - fails");
        expect($text)->toContain("Expected a to be b");
    });

    it("formats error results", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output);

        $example = new ExampleResult("errors", [], true);
        $example->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("not ok 1 - errors");
        expect($text)->toContain("boom");
    });

    it("formats pending results as SKIP", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output);

        $example = new ExampleResult("pending test", [], false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("ok 1 - pending test # SKIP pending");
    });

    it("formats results with nested contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("inner test", [$match]);
        $ctx = new ContextResult("my context", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("ok 1 - my context inner test");
    });

    context('streaming mode', function () {
        it('streams results via begin/printResult/end', function () {
            $output = new BufferedOutput();
            $formatter = new Tap($output);

            $formatter->begin();

            $match = MatchResult::passed();
            $example = new ExampleResult('passes', [$match]);
            $spec = new SpecificationResult('MySpec', [$example]);
            $formatter->printResult($spec);

            $example2 = new ExampleResult('also passes', [MatchResult::passed()]);
            $spec2 = new SpecificationResult('OtherSpec', [$example2]);
            $formatter->printResult($spec2);

            $suite = new SuiteResult([$spec, $spec2]);
            $formatter->end($suite);

            $text = $output->fetch();
            expect($text)->toContain('TAP version 13');
            expect($text)->toContain('ok 1 - passes');
            expect($text)->toContain('ok 2 - also passes');
            expect($text)->toContain('1..2');
        });
    });

    it("escapes special characters in messages", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output);

        $match = MatchResult::failed("a", "b", "Expected: \"a\"\nGot: \"b\"", __FILE__, __LINE__);
        $example = new ExampleResult("special chars", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("not ok 1");
    });

});
