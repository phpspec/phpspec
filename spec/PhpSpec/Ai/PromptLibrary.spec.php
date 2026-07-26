<?php

use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Filesystem;

describe(PromptLibrary::class, function () {

    it('reads a prompt artifact by name from the Prompts directory', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/next.txt') ? 'OUTSIDE-IN coaching' : '');
        $library = new PromptLibrary($fs);

        expect($library->read('next'))->toBe('OUTSIDE-IN coaching');
    });

    it('returns an empty string when the artifact is absent', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        $library = new PromptLibrary($fs);

        expect($library->read('missing'))->toBe('');
    });

    it('expands an @include line with the named prompt, in place', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => match (true) {
            str_ends_with($p, '/Prompts/commands/generate.txt') => "Head.\n\n@include instructions/spec-syntax\n\nTail.",
            str_ends_with($p, '/Prompts/instructions/spec-syntax.txt') => "THE-SYNTAX\n",
            default => '',
        });

        expect((new PromptLibrary($fs))->read('commands/generate'))->toBe("Head.\n\nTHE-SYNTAX\n\nTail.");
    });

    it('expands includes inside included prompts', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => match (true) {
            str_ends_with($p, '/Prompts/a.txt') => "@include b",
            str_ends_with($p, '/Prompts/b.txt') => "B says:\n@include c",
            str_ends_with($p, '/Prompts/c.txt') => 'C',
            default => '',
        });

        expect((new PromptLibrary($fs))->read('a'))->toBe("B says:\nC");
    });

    it('resolves an include of a missing prompt to nothing', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '/Prompts/a.txt'));
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/a.txt') ? "Head.\n@include gone\nTail." : '');

        expect((new PromptLibrary($fs))->read('a'))->toBe("Head.\n\nTail.");
    });

    it('throws on an include cycle instead of looping', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => match (true) {
            str_ends_with($p, '/Prompts/a.txt') => '@include b',
            str_ends_with($p, '/Prompts/b.txt') => '@include a',
            default => '',
        });

        expect(fn() => (new PromptLibrary($fs))->read('a'))
            ->toThrow(RuntimeException::class, 'Prompt include cycle: "a" is included while already being resolved (a > b).');
    });

    it('leaves a mid-line @include mention alone: only a full directive line expands', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/a.txt')
            ? 'Use @include name on its own line.'
            : 'SHOULD-NOT-APPEAR');

        expect((new PromptLibrary($fs))->read('a'))->toBe('Use @include name on its own line.');
    });

});
