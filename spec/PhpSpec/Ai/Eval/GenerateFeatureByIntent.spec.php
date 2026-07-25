<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E2 (eval) — when the instruction asks for a feature BY INTENT (no explicit
// path in the words), generate must still produce a .feature under features/,
// never a spec. Deterministic under the pipeline: the recorded bad reply (a
// spec) is never consulted.
describe('E2 generate: feature by intent', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
    });

    it('writes a .feature under features/, never a spec', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-feature-by-intent.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        // Assert on the value (not a boolean derived from it) so a failure carries
        // the actual path into --format=agent, keeping the JSON actionable.
        expect($replay->requests)->toBe([]);
        expect($outcome->proposals[0]->path)->toMatch('~^features/.+\.feature$~'); // a feature under features/
        expect($outcome->proposals[0]->path)->not()->toEndWith('.spec.php');       // not a spec
        expect($outcome->proposals[0]->new)->toContain('Feature:');                 // valid Gherkin
        expect($outcome->proposals[0]->new)->toContain('Scenario:');
    });

});
