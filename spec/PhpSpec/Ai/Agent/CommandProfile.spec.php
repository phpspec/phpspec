<?php

use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Filesystem;

// Commands are data: everything the pipeline needs to know about a command is
// declared in its prompt file's frontmatter (tools, answer channel, grounding
// sections, model params), so tuning a command is a text edit, never code.
describe(CommandProfile::class, function () {

    it('parses the manifest frontmatter and keeps the prose body', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(<<<'TXT'
        ---
        tools: [write_feature, write_steps]
        answer: tool_call
        grounding: [recency, tree]
        temperature: 0.2
        max_tokens: 4096
        ---
        You turn one instruction into ONE artifact.
        TXT);

        $profile = CommandProfile::load('generate', $fs);

        expect($profile->name)->toBe('generate');
        expect($profile->tools)->toBe(['write_feature', 'write_steps']);
        expect($profile->answer)->toBe('tool_call');
        expect($profile->grounding)->toBe(['recency', 'tree']);
        expect($profile->temperature)->toBe(0.2);
        expect($profile->maxTokens)->toBe(4096);
        expect($profile->body)->toContain('ONE artifact');
        expect($profile->body)->not()->toContain('tools:');
    });

    it('treats a file without frontmatter as pure prose with defaults', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("Just the prose.\n");

        $profile = CommandProfile::load('generate', $fs);

        expect($profile->body)->toBe('Just the prose.');
        expect($profile->tools)->toBe([]);
        expect($profile->answer)->toBe('prose');
        expect($profile->temperature)->toBeNull();
    });

    it('refuses to load a command with no prompt file', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect(fn() => CommandProfile::load('nope', $fs))
            ->toThrow(RuntimeException::class, 'Unknown AI command "nope": no "commands/nope.txt" prompt found.');
    });

    it('rejects an unknown answer channel instead of silently ignoring the typo', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("---\nanswer: tool-call\n---\nBody.");

        expect(fn() => CommandProfile::load('generate', $fs))->toThrow(RuntimeException::class);
    });

});
