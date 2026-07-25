<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E3 (eval) — "a spec for a Coupon ..." must produce a spec for the class named
// in the instruction, at a spec path, in valid phpspec-9 DSL — never the prompt's
// Calculator example, and never at the model's chosen path. The recorded reply
// picks the wrong path on purpose, so the derived one has to win.
describe('E3 generate: spec by intent', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    it('writes a spec for the instruction class, at a spec path, in valid DSL', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-spec-by-intent.json'), true);
        $replay = fn(array $ai, string $context): ?string => $rec['response']['text'];

        $agent = new GenerateAgent(new Configuration('.', $fs), $fs, $replay);
        $proposal = $agent->propose($rec['aiConfig'], $rec['instruction']);

        expect($proposal['path'])->toMatch('~^spec/.*\.spec\.php$~'); // a spec, at a spec path
        expect($proposal['path'])->toContain('Coupon');               // the class from the instruction
        expect($proposal['new'])->toContain('Coupon');                // spec is about Coupon
        expect($proposal['new'])->not()->toContain('Calculator');     // not the prompt's example
        expect($proposal['new'])->toContain('describe(');             // phpspec-9 DSL, not ObjectBehavior
    });

});
