<?php

use PhpSpec\Parallel\Wire;
use PhpSpec\Parallel\WireFormatter;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;
use Symfony\Component\Console\Output\BufferedOutput;

describe(WireFormatter::class, function () {
    it('sends each specification result whole on a line of its own, and nothing for the parts of one', function () {
        $output = new BufferedOutput();
        $formatter = new WireFormatter($output);
        $formatter->begin();

        $formatter->printResult(new ExampleResult('passes', [MatchResult::passed()]));
        $formatter->printResult(new SpecificationResult('Basket', [new ExampleResult('passes', [MatchResult::passed()])], 'spec/App/Basket.spec.php'));

        $lines = explode("\n", rtrim($output->fetch(), "\n"));

        expect($lines)->toHaveCount(1);
        expect((new Wire())->decode($lines[0]))->toBeAnInstanceOf(SpecificationResult::class);
    });

    it('ends the report with the line that tells the parent the worker finished', function () {
        $output = new BufferedOutput();

        (new WireFormatter($output))->end(new SuiteResult([]));

        expect((new Wire())->decode(rtrim($output->fetch(), "\n")))->toBe(true);
    });
});
