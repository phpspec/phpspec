<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E12 (eval, from the 2026-07-26 dogfood capture) — "update the TodoList.spec"
// in a project where the class lives at App\TodoList. The bare subject must be
// resolved against the project tree, so the model is shown the REAL current
// spec (not nothing), and the derived path lands on the existing file (an
// update, never a new sibling in the wrong directory). The recorded reply picks
// the flat wrong path on purpose; the resolved one has to win.
describe('E12 generate: a bare subject resolves against the project tree', function () {

    it('grounds the model in the real spec and updates it in place', function (Filesystem $fs) {
        $cwd = getcwd();
        $currentSpec = "<?php\ndescribe('TodoList', function () {\n    it(\"should add\", fn() => expect(null)->toBe(null));\n});\n";
        $dirs = ["$cwd/src", "$cwd/src/App", "$cwd/spec", "$cwd/spec/App"];
        $files = [
            "$cwd/src/App/TodoList.php" => "<?php\nnamespace App;\nclass TodoList {}\n",
            "$cwd/spec/App/TodoList.spec.php" => $currentSpec,
        ];

        allow($fs->exists())->toReturnUsing(fn(string $p): bool => in_array($p, $dirs, true) || isset($files[$p]));
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, $dirs, true));
        allow($fs->isFile())->toReturnUsing(fn(string $p): bool => isset($files[$p]));
        allow($fs->read())->toReturnUsing(fn(string $p): string => $files[$p] ?? '');
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match (true) {
            str_ends_with($p, '/src') => ['App'],
            str_ends_with($p, '/src/App') => ['TodoList.php'],
            str_ends_with($p, '/spec') => ['App'],
            str_ends_with($p, '/spec/App') => ['TodoList.spec.php'],
            default => [],
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-update-todolist-spec.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

        $context = $replay->requests[0]['messages'][1]->content;
        expect($context)->toContain('should add');                                // the REAL current spec reached the model
        expect($context)->toContain('TodoList.php');                              // and the tree names actual files

        expect($outcome->proposals[0]->path)->toBe('spec/App/TodoList.spec.php'); // resolved, not the model's flat guess
        expect($outcome->proposals[0]->isNew)->toBe(false);                       // an update of the existing spec
        expect($outcome->proposals[0]->old)->toContain('should add');
    });

});
