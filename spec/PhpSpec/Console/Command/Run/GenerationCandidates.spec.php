<?php

use PhpSpec\Console\Command\Run\GenerationCandidates;

describe(GenerationCandidates::class, function () {

    it('is empty when every list is empty', function () {
        expect((new GenerationCandidates())->isEmpty())->toBeTrue();
    });

    it('is not empty when any list has content', function () {
        $candidates = new GenerationCandidates(
            undefinedClassMethods: [['className' => 'App\\Calculator', 'methodName' => 'add', 'file' => '/spec.php', 'line' => 4]],
        );

        expect($candidates->isEmpty())->toBeFalse();
    });

    it('round-trips through toArray and fromArray', function () {
        $candidates = new GenerationCandidates(
            undefinedSteps: [['keyword' => 'Given', 'text' => 'a step']],
            stepsFile: 'acceptance_steps/steps.php',
            missingSpecClasses: ['App\\Foo' => 'App\\Basket'],
            missingStepClasses: ['App\\Bar'],
            missingMockTypes: ['App\\Repo'],
            undefinedMockInterfaceMethods: [['className' => 'App\\Repo', 'methodName' => 'find', 'file' => '/s.php', 'line' => 2]],
            undefinedClassMethods: [['className' => 'App\\Calculator', 'methodName' => 'add', 'file' => '/s.php', 'line' => 4]],
            fakeableMethods: [['className' => 'App\\Calculator', 'methodName' => 'total', 'fakeExpression' => '0', 'file' => '/s.php', 'line' => 6]],
        );

        $restored = GenerationCandidates::fromArray($candidates->toArray());

        expect($restored->toArray())->toBe($candidates->toArray());
    });

    it('round-trips through JSON', function () {
        $candidates = new GenerationCandidates(missingSpecClasses: ['App\\Foo' => 'App\\Basket']);

        $restored = GenerationCandidates::fromArray(json_decode((string) json_encode($candidates->toArray()), true));

        expect($restored->missingSpecClasses)->toBe(['App\\Foo' => 'App\\Basket']);
    });

    it('reads a missing-class list recorded before the describing class was kept', function () {
        $restored = GenerationCandidates::fromArray(['missingSpecClasses' => ['App\\Foo']]);

        expect($restored->missingSpecClasses)->toBe(['App\\Foo' => 'App\\Foo']);
    });

    it('narrows the missing classes to the one an offer names', function () {
        $candidates = new GenerationCandidates(missingSpecClasses: ['App\\Foo' => 'App\\Basket', 'App\\Bar' => 'App\\Basket']);

        expect($candidates->only('create_class', 'App\\Bar')->missingSpecClasses)->toBe(['App\\Bar' => 'App\\Basket']);
    });

    it('narrows the steps offer to the steps file it names', function () {
        $candidates = new GenerationCandidates(
            undefinedSteps: [['keyword' => 'Given', 'text' => 'a step']],
            missingStepClasses: ['App\\Till'],
            stepsFile: 'features/steps/steps.php',
        );

        $only = $candidates->only('create_steps', 'features/steps/steps.php');
        expect($only->undefinedSteps)->toBe([['keyword' => 'Given', 'text' => 'a step']]);
        expect($only->stepsFile)->toBe('features/steps/steps.php');
        expect($only->missingStepClasses)->toBe([]);
        expect($candidates->only('create_steps', 'features/steps/web.steps.php')->isEmpty())->toBeTrue();
    });

    it('reads undefined steps recorded per feature as one list', function () {
        $candidates = GenerationCandidates::fromArray(['undefinedSteps' => [
            'features/x.feature' => [['keyword' => 'Given', 'text' => 'one']],
            'features/y.feature' => [['keyword' => 'When', 'text' => 'two']],
        ]]);

        expect($candidates->undefinedSteps)->toBe([['keyword' => 'Given', 'text' => 'one'], ['keyword' => 'When', 'text' => 'two']]);
        expect($candidates->stepsFile)->toBe('features/steps/steps.php');
    });

    it('tolerates missing keys in fromArray', function () {
        $candidates = GenerationCandidates::fromArray([]);

        expect($candidates->isEmpty())->toBeTrue();
    });
});
