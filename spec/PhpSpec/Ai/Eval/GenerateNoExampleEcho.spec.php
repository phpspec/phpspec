<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E7 (eval, adversarial) — a prompt example must not leak into the output.
// Here the model echoes a Calculator wholesale (path AND content), but the
// path is derived from the instruction's subject, so it lands on Coupon, not
// Calculator. This locks in that the path attractor stays defeated.
describe('E7 generate: the prompt example never leaks into the path', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
    });

    it('derives the path from the instruction, not the echoed Calculator example', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-spec-echo-calculator.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        expect($outcome->proposals[0]->path)->toContain('Coupon');               // the instruction's subject
        expect($outcome->proposals[0]->path)->not()->toContain('Calculator');    // not the prompt example
    });

});
