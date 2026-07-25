<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E1 (eval) — when the instruction names an explicit feature path, generate
// must produce a .feature at THAT exact path, never a spec. Under the agent
// pipeline this is fully deterministic: the recorded model reply (the actual
// bad output from the bug: a spec at the model's own path) must never even be
// consulted.
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

    it('writes a .feature at the requested path without consulting the model', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-feature-explicit-path.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        expect($replay->requests)->toBe([]);                                       // deterministic: no model
        expect($outcome->proposals[0]->path)->toBe('features/user_adds_tasks.feature');
        expect($outcome->proposals[0]->new)->toContain('Feature:');                 // valid Gherkin
        expect($outcome->proposals[0]->new)->toContain('Scenario:');
    });

});
