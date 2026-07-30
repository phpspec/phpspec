<?php

use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Prompt;

// Commands are data: everything the pipeline needs to know about a command is
// declared in its prompt file's frontmatter (tools, answer channel, grounding
// sections, model params). A project override folds over the shipped manifest
// per key, so prose-only overrides keep the shipped machine contract.
describe(CommandProfile::class, function () {

    $manifest = <<<'TXT'
    ---
    tools: [write_feature, write_steps]
    answer: tool_call
    grounding: [recency, tree]
    temperature: 0.2
    max_tokens: 4096
    ---
    You turn one instruction into ONE artifact.
    TXT;

    it('parses the manifest frontmatter and keeps the prose body', function () use ($manifest) {
        $profile = CommandProfile::compose('generate', new Prompt('commands/generate', $manifest, Prompt::SHIPPED));

        expect($profile->name)->toBe('generate');
        expect($profile->tools)->toBe(['write_feature', 'write_steps']);
        expect($profile->answer)->toBe('tool_call');
        expect($profile->grounding)->toBe(['recency', 'tree']);
        expect($profile->temperature)->toBe(0.2);
        expect($profile->maxTokens)->toBe(4096);
        expect($profile->body)->toContain('ONE artifact');
        expect($profile->body)->not()->toContain('tools:');
        expect($profile->origin)->toBe(Prompt::SHIPPED);
    });

    it('reads max_turns from the frontmatter', function () {
        $profile = CommandProfile::compose('navigator', new Prompt('commands/navigator', "---\nmax_turns: 50\n---\nYou are the NAVIGATOR.", Prompt::SHIPPED));

        expect($profile->maxTurns)->toBe(50);
    });

    it('treats a file without frontmatter as pure prose with defaults', function () {
        $profile = CommandProfile::compose('generate', new Prompt('commands/generate', "Just the prose.\n", Prompt::SHIPPED));

        expect($profile->body)->toBe('Just the prose.');
        expect($profile->tools)->toBe([]);
        expect($profile->answer)->toBe('prose');
        expect($profile->temperature)->toBeNull();
    });

    it('lets a prose-only project override keep the whole shipped manifest', function () use ($manifest) {
        $profile = CommandProfile::compose(
            'generate',
            new Prompt('commands/generate', 'OUR house rules.', Prompt::PROJECT),
            new Prompt('commands/generate', $manifest, Prompt::SHIPPED),
        );

        expect($profile->body)->toBe('OUR house rules.');
        expect($profile->tools)->toBe(['write_feature', 'write_steps']);
        expect($profile->answer)->toBe('tool_call');
        expect($profile->grounding)->toBe(['recency', 'tree']);
        expect($profile->temperature)->toBe(0.2);
        expect($profile->maxTokens)->toBe(4096);
        expect($profile->origin)->toBe(Prompt::PROJECT);
    });

    it('folds override frontmatter per key: a temperature-only override keeps the shipped tools', function () use ($manifest) {
        $profile = CommandProfile::compose(
            'generate',
            new Prompt('commands/generate', "---\ntemperature: 0.9\n---\nOUR prose.", Prompt::PROJECT),
            new Prompt('commands/generate', $manifest, Prompt::SHIPPED),
        );

        expect($profile->temperature)->toBe(0.9);
        expect($profile->tools)->toBe(['write_feature', 'write_steps']);
        expect($profile->answer)->toBe('tool_call');
        expect($profile->body)->toBe('OUR prose.');
    });

    it('refuses to compose a command with no prompt layers', function () {
        expect(fn() => CommandProfile::compose('nope'))
            ->toThrow(RuntimeException::class, 'Unknown AI command "nope": no "commands/nope.txt" prompt found.');
    });

    it('rejects an unknown answer channel instead of silently ignoring the typo', function () {
        expect(fn() => CommandProfile::compose('generate', new Prompt('commands/generate', "---\nanswer: tool-call\n---\nBody.", Prompt::SHIPPED)))
            ->toThrow(RuntimeException::class);
    });

});
