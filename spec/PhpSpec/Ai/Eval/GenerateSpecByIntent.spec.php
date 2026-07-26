<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E3 (eval) — "a spec for a Coupon ..." must produce a spec for the class named
// in the instruction, at a spec path, in valid phpspec-9 DSL — never the
// prompt's example class, and never at the model's chosen path. The recorded
// tool call picks the wrong path on purpose, so the derived one has to win.
describe('E3 generate: spec by intent', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
    });

    it('writes a spec for the instruction class, at a spec path, in valid DSL', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-spec-by-intent.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        expect($outcome->proposals[0]->path)->toMatch('~^spec/.*\.spec\.php$~'); // a spec, at a spec path
        expect($outcome->proposals[0]->path)->toContain('Coupon');               // the class from the instruction
        expect($outcome->proposals[0]->new)->toContain('Coupon');                // spec is about Coupon
        expect($outcome->proposals[0]->new)->not()->toContain('Calculator');     // not the prompt's example
        expect($outcome->proposals[0]->new)->toContain('describe(');             // phpspec-9 DSL, not ObjectBehavior
    });

});
