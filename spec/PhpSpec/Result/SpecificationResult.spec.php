<?php

use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\ContextResult;

describe(SpecificationResult::class, function () {
    it('returns the specification title', function () {
        $result = new SpecificationResult('My Spec', []);
        expect($result->getTitle())->toBe('My Spec');
    });

    it('returns child example and context results', function () {
        $example = new ExampleResult('my example', []);
        $context = new ContextResult('my context', []);
        $result = new SpecificationResult('My Spec', [$example, $context]);

        expect($result->getResults())->toBe([$example, $context]);
    });
});
