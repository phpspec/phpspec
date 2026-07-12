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
            undefinedSteps: ['features/x.feature' => [['keyword' => 'Given', 'text' => 'a step']]],
            missingSpecClasses: ['App\\Foo'],
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
        $candidates = new GenerationCandidates(missingSpecClasses: ['App\\Foo']);

        $restored = GenerationCandidates::fromArray(json_decode((string) json_encode($candidates->toArray()), true));

        expect($restored->missingSpecClasses)->toBe(['App\\Foo']);
    });

    it('tolerates missing keys in fromArray', function () {
        $candidates = GenerationCandidates::fromArray([]);

        expect($candidates->isEmpty())->toBeTrue();
    });
});
