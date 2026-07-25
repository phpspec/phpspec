<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E4 (eval) — "implement the add method on the TodoList class" must produce
// plain implementation code under src/, never a spec or a feature.
describe('E4 generate: code by intent', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
    });

    it('writes implementation code under src/, never a spec', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-code-by-intent.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        expect($outcome->proposals[0]->path)->toMatch('~^src/.*\.php$~');        // under src/
        expect($outcome->proposals[0]->path)->not()->toEndWith('.spec.php');     // not a spec
        expect($outcome->proposals[0]->new)->toContain('class TodoList');        // the implementation
        expect($outcome->proposals[0]->new)->not()->toContain('describe(');      // plain PHP, not a spec
    });

});
