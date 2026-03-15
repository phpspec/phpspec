<?php

use PhpSpec\Extensions\FormatterBridge;
use PhpSpec\Extensions\FormatterExtension;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;
use Symfony\Component\Console\Output\BufferedOutput;

describe(FormatterBridge::class, function () {

    let('extension', fn() => new class extends FormatterExtension {
        public function getName(): string
        {
            return 'test';
        }

        public function formatPass(string $title): string
        {
            return "OK: $title";
        }

        public function formatFail(string $title, string $message): string
        {
            return "FAIL: $title - $message";
        }
    });

    it('outputs pass lines via the extension', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $example = new ExampleResult('works', []);
        $spec = new SpecificationResult('Test', [$example]);
        $suite = new SuiteResult([$spec]);

        $bridge->format($suite);

        expect($output->fetch())->toContain('OK: works');
    });

    it('outputs fail lines via the extension', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $failedMatch = MatchResult::failed(true, false, 'Expected true to be false', __FILE__, __LINE__);
        $example = new ExampleResult('broken', [$failedMatch]);
        $spec = new SpecificationResult('Test', [$example]);
        $suite = new SuiteResult([$spec]);

        $bridge->format($suite);

        expect($output->fetch())->toContain('FAIL: broken');
    });

    it('outputs pending lines via the extension', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $example = new ExampleResult('todo', [], false, true);
        $spec = new SpecificationResult('Test', [$example]);
        $suite = new SuiteResult([$spec]);

        $bridge->format($suite);

        expect($output->fetch())->toContain('PENDING: todo');
    });

    it('counts errors in the summary', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $example = new ExampleResult('crash', [], isError: true);
        $example->setError(new \PhpSpec\Specification\ExampleError('boom', new \RuntimeException('boom')));
        $spec = new SpecificationResult('Test', [$example]);
        $suite = new SuiteResult([$spec]);

        $bridge->format($suite);

        expect($output->fetch())->toContain('1 errors');
    });

    it('walks nested context results', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $example = new ExampleResult('nested', []);
        $context = new ContextResult('inner', [$example]);
        $spec = new SpecificationResult('Test', [$context]);
        $suite = new SuiteResult([$spec]);

        $bridge->format($suite);

        expect($output->fetch())->toContain('OK: nested');
    });

    it('shows duration when available', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $example = new ExampleResult('timed', []);
        $spec = new SpecificationResult('Test', [$example]);
        $suite = new SuiteResult([$spec]);
        $suite->setDuration(1.5);

        $bridge->format($suite);

        expect($output->fetch())->toContain('Finished in 1.5');
    });

    it('counts totals for the summary', function () {
        $output = new BufferedOutput();
        $bridge = new FormatterBridge($this->extension, $output);

        $pass = new ExampleResult('a', []);
        $pass2 = new ExampleResult('b', []);
        $spec = new SpecificationResult('Test', [$pass, $pass2]);
        $suite = new SuiteResult([$spec]);

        $bridge->format($suite);

        $text = $output->fetch();
        expect($text)->toContain('2 examples');
        expect($text)->toContain('2 passed');
    });
});
