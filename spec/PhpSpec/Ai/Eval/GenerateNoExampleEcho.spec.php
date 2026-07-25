<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E7 (eval, adversarial) — the prompt's Calculator example must not leak into the
// output. Here the model echoes it wholesale (Calculator path AND content), but
// the path is derived from the instruction's subject, so it lands on Coupon, not
// Calculator. This locks in that the path attractor stays defeated.
describe('E7 generate: the prompt example never leaks into the path', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    it('derives the path from the instruction, not the echoed Calculator example', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-spec-echo-calculator.json'), true);
        $replay = fn(array $ai, string $context): ?string => $rec['response']['text'];

        $agent = new GenerateAgent(new Configuration('.', $fs), $fs, $replay);
        $proposal = $agent->propose($rec['aiConfig'], $rec['instruction']);

        expect($proposal['path'])->toContain('Coupon');       // the instruction's subject
        expect($proposal['path'])->not()->toContain('Calculator'); // not the prompt example
    });

});
