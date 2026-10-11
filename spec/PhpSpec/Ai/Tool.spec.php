<?php

use PhpSpec\Ai\Tool;

describe(Tool::class, function () {

    it('describes each parameter to the model, one with a default left optional', function () {
        $tool = Tool::make('decline_refactoring', 'Says why nothing is worth changing.', [
            'reason' => ['type' => 'string', 'description' => 'why'],
            'mood' => ['type' => 'string', 'enum' => ['calm', 'firm'], 'default' => 'calm'],
        ], fn(array $arguments): string => $arguments['reason']);

        expect($tool->getParameterSchema())->toBe([
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string', 'description' => 'why'],
                'mood' => ['type' => 'string', 'enum' => ['calm', 'firm']],
            ],
            'required' => ['reason'],
        ]);
        expect($tool->execute(['reason' => 'clean']))->toBe('clean');
    });

    it('passes a list parameter what it holds: strings, or objects with their own properties', function () {
        $file = [
            'type' => 'object',
            'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']],
            'required' => ['path', 'content'],
        ];

        $tool = Tool::make('propose_step', 'One baby step.', [
            'files' => ['type' => 'array', 'description' => 'every file the step writes', 'items' => $file],
            'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
        ], fn() => null);

        expect($tool->getParameterSchema()['properties'])->toBe([
            'files' => ['type' => 'array', 'description' => 'every file the step writes', 'items' => $file],
            'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);
    });
});
