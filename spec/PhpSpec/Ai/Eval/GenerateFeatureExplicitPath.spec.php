<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E1 (eval) — when the instruction names an explicit feature path, generate
// must produce a .feature at THAT exact path, never a spec. The model is
// consulted for the scenario content, but the recorded bad reply (the actual
// bug output: a spec at the model's own path) must not survive the rails: the
// derived path wins and non-Gherkin content falls back to the skeleton.
describe('E1 generate: feature at an explicit path', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
    });

    it('writes a .feature at the requested path even when the model answers with a spec', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-feature-explicit-path.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        expect($replay->requests)->toHaveLength(2);                                // consulted, then re-asked once
        expect($outcome->proposals[0]->path)->toBe('features/user_adds_tasks.feature');
        expect($outcome->proposals[0]->new)->toContain('Feature:');                 // valid Gherkin
        expect($outcome->proposals[0]->new)->toContain('Scenario:');
        expect($outcome->proposals[0]->new)->not()->toContain('describe(');         // the recorded spec died at the rail
    });

});
