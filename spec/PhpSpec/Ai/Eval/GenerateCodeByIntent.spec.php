<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E4 (eval) — "implement the add method on the TodoList class" must produce plain
// implementation code under src/, never a spec or a feature.
describe('E4 generate: code by intent', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    it('writes implementation code under src/, never a spec', function (Filesystem $fs) {
        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-code-by-intent.json'), true);
        $replay = fn(array $ai, string $context): ?string => $rec['response']['text'];

        $agent = new GenerateAgent(new Configuration('.', $fs), $fs, $replay);
        $proposal = $agent->propose($rec['aiConfig'], $rec['instruction']);

        expect($proposal['path'])->toMatch('~^src/.*\.php$~'); // under src/
        expect($proposal['path'])->not()->toEndWith('.spec.php'); // not a spec
        expect($proposal['new'])->toContain('class TodoList'); // the implementation
        expect($proposal['new'])->not()->toContain('describe('); // plain PHP, not a spec
    });

});
