<?php

use PhpSpec\Extensions\FormatterExtension;

describe(FormatterExtension::class, function () {

    let('formatter', fn() => new class extends FormatterExtension {
        public function getName(): string
        {
            return 'test';
        }

        public function formatPass(string $title): string
        {
            return "PASS: $title";
        }

        public function formatFail(string $title, string $message): string
        {
            return "FAIL: $title - $message";
        }
    });

    it('requires getName', function () {
        expect($this->formatter->getName())->toBe('test');
    });

    it('requires formatPass', function () {
        expect($this->formatter->formatPass('works'))->toBe('PASS: works');
    });

    it('requires formatFail', function () {
        expect($this->formatter->formatFail('broken', 'bad'))->toBe('FAIL: broken - bad');
    });

    it('has a default formatPending', function () {
        expect($this->formatter->formatPending('todo'))->toBe('PENDING: todo');
    });

    it('defaults formatError to formatFail', function () {
        expect($this->formatter->formatError('boom', 'error'))->toBe('FAIL: boom - error');
    });

    it('has a default formatSummary', function () {
        $summary = $this->formatter->formatSummary(10, 8, 1, 1, 0);
        expect($summary)->toBe('10 examples (8 passed, 1 failed, 1 pending, 0 errors)');
    });
});
