<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E2 (eval, red-first) — when the instruction asks for a feature BY INTENT (no
// explicit path in the words), /generate must still produce a .feature under
// features/, never a spec. RED against current /generate: with no path to parse
// it falls back to the model's choice, which returned a spec (the bug). Goes
// green once feature intent routes to a deterministic feature file.
describe('E2 generate: feature by intent', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    it('writes a .feature under features/, never a spec', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-feature-by-intent.json'), true);
        $replay = fn(array $ai, string $context): ?string => $rec['response']['text'];

        $agent = new GenerateAgent(new Configuration('.', $fs), $fs, $replay);
        $proposal = $agent->propose($rec['aiConfig'], $rec['instruction']);

        // Assert on the value (not a boolean derived from it) so a failure carries
        // the actual path into --format=agent, keeping the JSON actionable.
        expect($proposal['path'])->toMatch('~^features/.+\.feature$~'); // a feature under features/
        expect($proposal['path'])->not()->toEndWith('.spec.php');       // not a spec
        expect($proposal['new'])->toContain('Feature:');                // valid Gherkin
        expect($proposal['new'])->toContain('Scenario:');
    });

});
