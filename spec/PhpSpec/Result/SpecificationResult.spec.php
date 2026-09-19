<?php

use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;

describe(SpecificationResult::class, function () {

    it('is blocked when a describe block could not run for want of a class', function () {
        $error = new ExampleError('Class "App\Calculator" not found', new \Error('Class "App\Calculator" not found'));
        $describe = new ContextResult('App\Calculator', []);
        $describe->setError($error);

        expect((new SpecificationResult('Calculator', [$describe]))->isBlockedOnMissingClass())->toBeTrue();
    });

    it('is not blocked by a describe block that broke for any other reason', function () {
        $describe = new ContextResult('App\Calculator', []);
        $describe->setError(new ExampleError('boom', new \RuntimeException('boom')));

        expect((new SpecificationResult('Calculator', [$describe]))->isBlockedOnMissingClass())->toBeFalse();
    });

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
