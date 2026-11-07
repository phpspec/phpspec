<?php

use PhpSpec\ProjectRoot;
use PhpSpec\Report\Formatter\Tap;
use PhpSpec\Result\Outcomes;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\StoryBDD\StepError;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Tap::class, function() {

    this('outcomes', fn() => new Outcomes(ProjectRoot::at('/project')));

    it("reports each scenario as one test point, judged by its steps as the run judges it", function() {
        $output = new BufferedOutput();
        $failed = new StepResult('Then the total is refunded', 'failure');
        $failed->setError(new StepError('Expected 0 to be 5', new RuntimeException('Expected 0 to be 5')));
        $failed->setMatch(MatchResult::failed(0, 5, 'Expected 0 to be 5', '/project/features/steps/shop.php', 7));
        $scenarios = [
            new ScenarioResult('Pays', [new StepResult('When I pay', 'passed')]),
            new ScenarioResult('Refunds', [new StepResult('When I refund', 'passed'), $failed, new StepResult('Then I see a receipt', 'skipped')]),
            new ScenarioResult('Later', [new StepResult('When later', 'pending', 'Needs the gateway')]),
            new ScenarioResult('Unknown', [new StepResult('When something new', 'undefined')]),
            new ScenarioResult('Closed', [new StepResult('Given the shop', 'skipped', 'Closed on Sundays'), new StepResult('When I pay', 'skipped')]),
            new ScenarioResult('Waits, then lacks a step', [new StepResult('When later', 'pending', 'Needs the gateway'), new StepResult('Then something new', 'undefined')]),
        ];

        (new Tap($output, $this->outcomes))->format(new SuiteResult([new FeatureResult('Shop', $scenarios)]));

        $text = str_replace(PHP_EOL, "\n", $output->fetch());
        expect($text)->toContain("1..6");
        expect($text)->toContain("ok 1 - Shop > Pays\n");
        expect($text)->toContain("not ok 2 - Shop > Refunds\n  ---\n  message: Expected 0 to be 5\n  step: Then the total is refunded\n  at: features/steps/shop.php:7\n  ...");
        expect($text)->toContain("ok 3 - Shop > Later # SKIP Needs the gateway");
        expect($text)->toContain("not ok 4 - Shop > Unknown\n  ---\n  message: \"Undefined step: When something new\"\n  step: When something new\n  ...");
        expect($text)->toContain("ok 5 - Shop > Closed # SKIP Closed on Sundays");
        expect($text)->toContain("not ok 6 - Shop > Waits, then lacks a step\n");
    });

    it("formats passing results", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output, $this->outcomes);

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
        $formatter = new Tap($output, $this->outcomes);

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
        $formatter = new Tap($output, $this->outcomes);

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
        $formatter = new Tap($output, $this->outcomes);

        $example = new ExampleResult("pending test", [], false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("ok 1 - pending test # SKIP pending");
    });

    it("gives the SKIP directive the reason a pending or skipped example gave", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output, $this->outcomes);

        $pending = new ExampleResult("is pending", [], isPending: true, reason: "Needs the rates API");
        $skipped = new ExampleResult("is skipped", [], isSkipped: true, reason: "No network here");
        $bare = new ExampleResult("is skipped bare", [], isSkipped: true);
        $suite = new SuiteResult([new SpecificationResult("MySpec", [$pending, $skipped, $bare])]);

        $formatter->format($suite);
        $text = $output->fetch();

        expect($text)->toContain("ok 1 - is pending # SKIP Needs the rates API");
        expect($text)->toContain("ok 2 - is skipped # SKIP No network here");
        expect($text)->toContain("ok 3 - is skipped bare # SKIP skipped");
    });

    it("formats results with nested contexts", function() {
        $output = new BufferedOutput();
        $formatter = new Tap($output, $this->outcomes);

        $match = MatchResult::passed();
        $example = new ExampleResult("inner test", [$match]);
        $ctx = new ContextResult("my context", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("ok 1 - my context > inner test");
    });

    context('streaming mode', function () {
        it('streams results via begin/printResult/end', function () {
            $output = new BufferedOutput();
            $formatter = new Tap($output, $this->outcomes);

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
        $formatter = new Tap($output, $this->outcomes);

        $match = MatchResult::failed("a", "b", "Expected: \"a\"\nGot: \"b\"", __FILE__, __LINE__);
        $example = new ExampleResult("special chars", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("not ok 1");
    });

});
