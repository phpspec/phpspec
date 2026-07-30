<?php

use PhpSpec\Ai\Prompt;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Filesystem;

// The library resolves a prompt name through two layers: the project's
// .phpspec/prompts directory first (the team's voice, committable), then the
// shipped Prompts directory (package code). One resolution seam serves every
// consumer.
describe(PromptLibrary::class, function () {

    $shippedOnly = fn(Filesystem $shipped) => new PromptLibrary(null, $shipped, '/project');

    it('reads a shipped prompt artifact by name from the Prompts directory', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/next.txt') ? 'OUTSIDE-IN coaching' : '');

        expect($shippedOnly($fs)->read('next'))->toBe('OUTSIDE-IN coaching');
    });

    it('returns an empty string when the artifact is absent everywhere', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturn(false);

        expect($shippedOnly($fs)->read('missing'))->toBe('');
    });

    it('prefers a project override over the shipped prompt', function (Filesystem $project, Filesystem $shipped) {
        allow($project->exists())->toReturnUsing(fn(string $p): bool => $p === '/project/.phpspec/prompts/commands/next.txt');
        allow($project->read())->toReturn('OUR coaching');
        allow($shipped->exists())->toReturn(true);
        allow($shipped->read())->toReturn('SHIPPED coaching');

        $library = new PromptLibrary($project, $shipped, '/project');

        expect($library->read('commands/next'))->toBe('OUR coaching');
    });

    it('falls back to the shipped prompt when the project has no override', function (Filesystem $project, Filesystem $shipped) {
        allow($project->exists())->toReturn(false);
        allow($shipped->exists())->toReturn(true);
        allow($shipped->read())->toReturn('SHIPPED coaching');

        $library = new PromptLibrary($project, $shipped, '/project');

        expect($library->read('commands/next'))->toBe('SHIPPED coaching');
    });

    it('stacks every existing layer, project first, each naming its origin', function (Filesystem $project, Filesystem $shipped) {
        allow($project->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, '.phpspec/prompts/'));
        allow($project->read())->toReturn('OURS');
        allow($shipped->exists())->toReturn(true);
        allow($shipped->read())->toReturn('THEIRS');

        $stack = (new PromptLibrary($project, $shipped, '/project'))->stack('commands/generate');

        expect($stack)->toHaveCount(2);
        expect($stack[0]->origin)->toBe(Prompt::PROJECT);
        expect($stack[0]->text)->toBe('OURS');
        expect($stack[1]->origin)->toBe(Prompt::SHIPPED);
        expect($stack[1]->text)->toBe('THEIRS');
    });

    it('stacks a single shipped layer when no override exists', function (Filesystem $project, Filesystem $shipped) {
        allow($project->exists())->toReturn(false);
        allow($shipped->exists())->toReturn(true);
        allow($shipped->read())->toReturn('THEIRS');

        $stack = (new PromptLibrary($project, $shipped, '/project'))->stack('commands/generate');

        expect($stack)->toHaveCount(1);
        expect($stack[0]->origin)->toBe(Prompt::SHIPPED);
    });

    it('expands an @include line with the named prompt, in place', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => match (true) {
            str_ends_with($p, '/Prompts/commands/generate.txt') => "Head.\n\n@include instructions/spec-syntax\n\nTail.",
            str_ends_with($p, '/Prompts/instructions/spec-syntax.txt') => "THE-SYNTAX\n",
            default => '',
        });

        expect($shippedOnly($fs)->read('commands/generate'))->toBe("Head.\n\nTHE-SYNTAX\n\nTail.");
    });

    it('resolves an @include inside a shipped prompt project-first', function (Filesystem $project, Filesystem $shipped) {
        allow($project->exists())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.phpspec/prompts/instructions/spec-syntax.txt'));
        allow($project->read())->toReturn('OUR-SYNTAX');
        allow($shipped->exists())->toReturn(true);
        allow($shipped->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/commands/generate.txt')
            ? "@include instructions/spec-syntax"
            : 'SHIPPED-SYNTAX');

        $library = new PromptLibrary($project, $shipped, '/project');

        expect($library->read('commands/generate'))->toBe('OUR-SYNTAX');
    });

    it('expands includes inside included prompts', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => match (true) {
            str_ends_with($p, '/Prompts/a.txt') => "@include b",
            str_ends_with($p, '/Prompts/b.txt') => "B says:\n@include c",
            str_ends_with($p, '/Prompts/c.txt') => 'C',
            default => '',
        });

        expect($shippedOnly($fs)->read('a'))->toBe("B says:\nC");
    });

    it('resolves an include of a missing prompt to nothing', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '/Prompts/a.txt'));
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/a.txt') ? "Head.\n@include gone\nTail." : '');

        expect($shippedOnly($fs)->read('a'))->toBe("Head.\n\nTail.");
    });

    it('throws on an include cycle instead of looping', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => match (true) {
            str_ends_with($p, '/Prompts/a.txt') => '@include b',
            str_ends_with($p, '/Prompts/b.txt') => '@include a',
            default => '',
        });

        expect(fn() => $shippedOnly($fs)->read('a'))
            ->toThrow(RuntimeException::class, 'Prompt include cycle: "a" is included while already being resolved (a > b).');
    });

    it('makes a self-including override a clear cycle error, not a silent loop', function (Filesystem $project, Filesystem $shipped) {
        allow($project->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, '.phpspec/prompts/commands/next.txt'));
        allow($project->read())->toReturn('@include commands/next');
        allow($shipped->exists())->toReturn(true);
        allow($shipped->read())->toReturn('SHIPPED');

        expect(fn() => (new PromptLibrary($project, $shipped, '/project'))->read('commands/next'))
            ->toThrow(RuntimeException::class);
    });

    it('leaves a mid-line @include mention alone: only a full directive line expands', function (Filesystem $fs) use ($shippedOnly) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/a.txt')
            ? 'Use @include name on its own line.'
            : 'SHOULD-NOT-APPEAR');

        expect($shippedOnly($fs)->read('a'))->toBe('Use @include name on its own line.');
    });

});
