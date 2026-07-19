<?php

use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;

describe(SuiteSummary::class, function () {

    it('reports green status when nothing failed', function () {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Greeter', [
                new ExampleResult('greets', []),
            ]),
        ]);

        $summary = SuiteSummary::fromSuiteResult($suite);

        expect($summary->status())->toBe('green');
        expect($summary->isGreen())->toBe(true);
        expect($summary->isRed())->toBe(false);
    });

    it('reports red status and names the failing example with its subject', function () {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Calculator', [
                new ExampleResult('adds numbers', [], isError: true),
            ]),
        ]);

        $summary = SuiteSummary::fromSuiteResult($suite);

        expect($summary->isRed())->toBe(true);
        expect($summary->failing())->toBe([
            ['subject' => 'App\\Calculator', 'example' => 'adds numbers', 'error' => ''],
        ]);
    });

    it('carries the error message of a failing example so the navigator sees why', function () {
        $example = new ExampleResult('adds numbers', [], isError: true);
        $example->setError(new \PhpSpec\Specification\ExampleError(
            'Expected 5 but got 3',
            new \RuntimeException('Expected 5 but got 3'),
        ));

        $suite = new SuiteResult([
            new SpecificationResult('App\\Calculator', [$example]),
        ]);

        expect(SuiteSummary::fromSuiteResult($suite)->failing())->toBe([
            ['subject' => 'App\\Calculator', 'example' => 'adds numbers', 'error' => 'Expected 5 but got 3'],
        ]);
    });

    it('collapses whitespace and clips a long error message to 300 characters', function () {
        $long = str_repeat('very long assertion dump ', 40); // ~1000 chars, with newlines collapsed
        $example = new ExampleResult('adds numbers', [], isError: true);
        $example->setError(new \PhpSpec\Specification\ExampleError(
            "line one\n" . $long,
            new \RuntimeException('boom'),
        ));

        $suite = new SuiteResult([
            new SpecificationResult('App\\Calculator', [$example]),
        ]);

        $error = SuiteSummary::fromSuiteResult($suite)->failing()[0]['error'];

        expect(mb_strlen($error))->toBe(300);
        expect($error)->toContain('line one very long');
        expect($error)->toEndWith('…');
    });

    it('round-trips the error field through toArray/fromArray', function () {
        $example = new ExampleResult('adds numbers', [], isError: true);
        $example->setError(new \PhpSpec\Specification\ExampleError(
            'Expected 5 but got 3',
            new \RuntimeException('Expected 5 but got 3'),
        ));
        $summary = SuiteSummary::fromSuiteResult(new SuiteResult([
            new SpecificationResult('App\\Calculator', [$example]),
        ]));

        expect(SuiteSummary::fromArray($summary->toArray())->failing())->toBe($summary->failing());
    });

    it('tolerates malformed failing/pending data from a decoded report', function () {
        $summary = SuiteSummary::fromArray([
            'status' => 'red',
            'counts' => ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            'failing' => 'not-an-array',
            'pending' => ['a scalar item', ['subject' => 'App\\X', 'example' => 'works', 'error' => 'boom']],
        ]);

        expect($summary->failing())->toBe([]);
        expect($summary->pending())->toBe([
            ['subject' => 'App\\X', 'example' => 'works', 'error' => 'boom'],
        ]);
    });

    it('defaults the error field when a legacy report omits it', function () {
        $summary = SuiteSummary::fromArray([
            'status' => 'red',
            'counts' => ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            'failing' => [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
            'pending' => [],
        ]);

        expect($summary->failing())->toBe([
            ['subject' => 'App\\Calculator', 'example' => 'adds numbers', 'error' => ''],
        ]);
    });

    it('counts examples by outcome', function () {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Calculator', [
                new ExampleResult('adds', []),
                new ExampleResult('subtracts', [], isError: true),
                new ExampleResult('multiplies', [], isPending: true),
            ]),
        ]);

        expect(SuiteSummary::fromSuiteResult($suite)->counts())->toBe([
            'examples' => 3,
            'passes' => 1,
            'failures' => 0,
            'errors' => 1,
            'pending' => 1,
        ]);
    });

    it('collects pending examples and exposes the nearest gap', function () {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('holds products', []),
                new ExampleResult('applies a discount code', [], isPending: true),
            ]),
        ]);

        $summary = SuiteSummary::fromSuiteResult($suite);

        expect($summary->isGreen())->toBe(true);
        expect($summary->pending())->toBe([
            ['subject' => 'App\\Basket', 'example' => 'applies a discount code', 'error' => ''],
        ]);
        expect($summary->nearestPendingGap())->toBe([
            'subject' => 'App\\Basket',
            'example' => 'applies a discount code',
            'error' => '',
        ]);
    });

    it('summarises features: hasFeatures, per-feature red/todo status, and featuresAreGreen', function () {
        $suite = new SuiteResult([
            new FeatureResult('Adding', [
                new ScenarioResult('adds two numbers', [
                    new StepResult('Given a calculator', 'passed'),
                    new StepResult('When I add 2 and 3', 'failure'),
                ]),
            ], 'features/adding.feature'),
            new FeatureResult('Subtracting', [
                new ScenarioResult('subtracts two numbers', [
                    new StepResult('Given a calculator', 'undefined'),
                ]),
            ], 'features/subtracting.feature'),
        ]);

        $summary = SuiteSummary::fromSuiteResult($suite);

        expect($summary->hasFeatures())->toBe(true);
        expect($summary->featuresAreGreen())->toBe(false);
        expect($summary->features())->toBe([
            ['path' => 'features/adding.feature', 'status' => 'red', 'undefined' => 0],
            ['path' => 'features/subtracting.feature', 'status' => 'todo', 'undefined' => 1],
        ]);
        expect($summary->redFeature())->toBe(['path' => 'features/adding.feature', 'status' => 'red', 'undefined' => 0]);
    });

    it('reports features as green when every step passes', function () {
        $suite = new SuiteResult([
            new FeatureResult('Adding', [
                new ScenarioResult('adds', [
                    new StepResult('Given a calculator', 'passed'),
                    new StepResult('Then it sums', 'passed'),
                ]),
            ], 'features/adding.feature'),
        ]);

        $summary = SuiteSummary::fromSuiteResult($suite);

        expect($summary->hasFeatures())->toBe(true);
        expect($summary->featuresAreGreen())->toBe(true);
        expect($summary->redFeature())->toBeNull();
    });

    it('reports no features for a spec-only suite', function () {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Greeter', [new ExampleResult('greets', [])]),
        ]);

        $summary = SuiteSummary::fromSuiteResult($suite);

        expect($summary->hasFeatures())->toBe(false);
        expect($summary->featuresAreGreen())->toBe(false);
        expect($summary->features())->toBe([]);
    });

    it('round-trips feature data through toArray/fromArray', function () {
        $suite = new SuiteResult([
            new FeatureResult('Adding', [
                new ScenarioResult('adds', [new StepResult('Given a calculator', 'undefined')]),
            ], 'features/adding.feature'),
        ]);
        $summary = SuiteSummary::fromSuiteResult($suite);

        $restored = SuiteSummary::fromArray($summary->toArray());

        expect($restored->hasFeatures())->toBe(true);
        expect($restored->features())->toBe($summary->features());
        expect($restored->featureCounts())->toBe($summary->featureCounts());
    });

    it('treats a legacy report without feature data as having no features', function () {
        $summary = SuiteSummary::fromArray([
            'status' => 'green',
            'counts' => ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            'failing' => [],
            'pending' => [],
        ]);

        expect($summary->hasFeatures())->toBe(false);
        expect($summary->features())->toBe([]);
    });

    it('keeps the subject when examples are nested in a context', function () {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Calculator', [
                new ContextResult('when adding', [
                    new ExampleResult('sums two numbers', [], isError: true),
                ]),
            ]),
        ]);

        expect(SuiteSummary::fromSuiteResult($suite)->failing())->toBe([
            ['subject' => 'App\\Calculator', 'example' => 'sums two numbers', 'error' => ''],
        ]);
    });

});
