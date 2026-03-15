<?php

use PhpSpec\Console\Command\Refactor\RefactorResult;

describe(RefactorResult::class, function () {

    it('stores success result properties', function () {
        $result = new RefactorResult(
            success: true,
            technique: 'Extract Method',
            description: 'Extracted add() from calculate()',
            diff: '+ private function add()',
            specOutput: '5 examples (5 passes)',
        );

        expect($result->success)->toBeTrue();
        expect($result->technique)->toBe('Extract Method');
        expect($result->description)->toBe('Extracted add() from calculate()');
        expect($result->diff)->toContain('add()');
        expect($result->specOutput)->toContain('5 passes');
    });

    it('stores failure result properties', function () {
        $result = new RefactorResult(
            success: false,
            technique: 'Inline Variable',
            description: 'Attempted to inline $temp',
            diff: '- $temp = $a + $b;',
            specOutput: '1 failure',
        );

        expect($result->success)->toBeFalse();
        expect($result->technique)->toBe('Inline Variable');
        expect($result->specOutput)->toContain('failure');
    });

    it('stores empty diff for no-change result', function () {
        $result = new RefactorResult(
            success: false,
            technique: 'None',
            description: 'No refactoring needed',
            diff: '',
            specOutput: '',
        );

        expect($result->diff)->toBe('');
        expect($result->technique)->toBe('None');
    });
});
