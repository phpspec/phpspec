<?php

use PhpSpec\Parallel\Wire;
use PhpSpec\Parallel\WireFormatter;
use PhpSpec\Parallel\WireMark;
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
        $formatter->ran();

        $lines = explode("\n", rtrim($output->fetch(), "\n"));

        expect($lines)->toHaveCount(2);
        expect((new Wire())->decode($lines[0]))->toBeAnInstanceOf(SpecificationResult::class);
    });

    it('ends the report with the line that tells the parent the worker finished', function () {
        $output = new BufferedOutput();

        (new WireFormatter($output))->end(new SuiteResult([]));

        expect((new Wire())->decode(rtrim($output->fetch(), "\n")))->toBe(WireMark::Ended);
    });

    context('sending a result with what follows it', function () {
        this('counted', fn() => new class extends BufferedOutput {
            public int $writes = 0;

            protected function doWrite(string $message, bool $newline): void
            {
                $this->writes++;
                parent::doWrite($message, $newline);
            }
        });

        this('result', fn() => new SpecificationResult('Basket', [new ExampleResult('passes', [MatchResult::passed()])], 'spec/App/Basket.spec.php'));

        it('sends a result and the line saying the worker ran its file in one write, so they arrive together', function () {
            $formatter = new WireFormatter($this->counted);

            $formatter->printResult($this->result);

            expect($this->counted->writes)->toBe(0);

            $formatter->ran();

            expect($this->counted->writes)->toBe(1);
            expect(substr_count($this->counted->fetch(), "\n"))->toBe(2);
        });

        it('sends a result still held with the line that ends the report', function () {
            $formatter = new WireFormatter($this->counted);

            $formatter->printResult($this->result);
            $formatter->end(new SuiteResult([]));

            expect($this->counted->writes)->toBe(1);
            expect(substr_count($this->counted->fetch(), "\n"))->toBe(2);
        });

        it('sends a result held already when another comes', function () {
            $formatter = new WireFormatter($this->counted);

            $formatter->printResult($this->result);
            $formatter->printResult($this->result);

            expect($this->counted->writes)->toBe(1);
            expect(substr_count($this->counted->fetch(), "\n"))->toBe(1);
        });
    });

    it('says the worker ran the file it was handed, on a line of its own', function () {
        $output = new BufferedOutput();

        (new WireFormatter($output))->ran();

        expect($output->fetch())->toBe((new Wire())->ran() . "\n");
    });
});
