<?php

use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\SpecificationResult;
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
            ['subject' => 'App\\Calculator', 'example' => 'adds numbers'],
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
            ['subject' => 'App\\Basket', 'example' => 'applies a discount code'],
        ]);
        expect($summary->nearestPendingGap())->toBe([
            'subject' => 'App\\Basket',
            'example' => 'applies a discount code',
        ]);
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
            ['subject' => 'App\\Calculator', 'example' => 'sums two numbers'],
        ]);
    });

});
