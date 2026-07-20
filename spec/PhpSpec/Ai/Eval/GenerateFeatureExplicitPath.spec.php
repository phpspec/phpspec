<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E1 (eval, red-first) — when the instruction names an explicit feature path,
// /generate must produce a .feature at THAT exact path, never a spec. The model
// reply is replayed from a recorded fixture (the actual bad output from the bug),
// so the grader is deterministic and needs no provider or network.
//
// RED against current /generate, which honours whatever path the model chose —
// here spec/App/Calculator.spec.php. Goes green once GenerateAgent parses the
// explicit path from the instruction and generates a feature for a .feature path.
describe('E1 generate: feature at an explicit path', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    it('writes a .feature at the requested path, never a spec', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-feature-explicit-path.json'), true);
        $replay = fn(array $ai, string $context): ?string => $rec['response']['text'];

        $agent = new GenerateAgent(new Configuration('.', $fs), $fs, $replay);
        $proposal = $agent->propose($rec['aiConfig'], $rec['instruction']);

        expect($proposal['path'])->toBe('features/user_adds_tasks.feature');   // right path/type
        expect(str_ends_with($proposal['path'], '.feature'))->toBe(true);       // a feature file
        expect(str_starts_with($proposal['path'], 'spec/'))->toBe(false);       // not a spec
        expect($proposal['new'])->toContain('Feature:');                        // valid Gherkin
        expect($proposal['new'])->toContain('Scenario:');
    });

});
