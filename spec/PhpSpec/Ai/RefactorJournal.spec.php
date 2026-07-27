<?php

use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Filesystem;

describe(RefactorJournal::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
    });

    it('records an applied refactoring as one JSON line with a timestamp', function (Filesystem $fs) {
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });

        (new RefactorJournal($fs))->record('App\\TodoList', 'Extract Method', 'Pulled hasTask out');

        $path = array_key_first($written);
        expect($path)->toContain('.phpspec/ai/journal.jsonl');
        $entry = json_decode(trim($written[$path]), true);
        expect($entry['target'])->toBe('App\\TodoList');
        expect($entry['technique'])->toBe('Extract Method');
        expect($entry['at'])->toBeGreaterThan(0);
    });

    it('appends to an existing journal instead of overwriting it', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl'));
        allow($fs->read())->toReturn('{"at":100,"command":"refactor","target":"App\\\\Old","technique":"Rename","description":"x"}' . "\n");
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });

        (new RefactorJournal($fs))->record('App\\TodoList', 'Inline Method', 'Inlined it');

        $content = implode('', $written);
        expect($content)->toContain('App\\\\Old');
        expect($content)->toContain('Inline Method');
    });

    context('unchangedTargets', function () {

        it('lists a journalled class whose source has not changed since its refactoring', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl') || $p === '/project/src/App/TodoList.php');
            allow($fs->read())->toReturn('{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Extract Method","description":"x"}' . "\n");
            allow($fs->mtime())->toReturn(900);

            expect((new RefactorJournal($fs))->unchangedTargets('/project/src'))->toBe(['App\\TodoList']);
        });

        it('omits a class modified after its refactoring', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl') || $p === '/project/src/App/TodoList.php');
            allow($fs->read())->toReturn('{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Extract Method","description":"x"}' . "\n");
            allow($fs->mtime())->toReturn(1100);

            expect((new RefactorJournal($fs))->unchangedTargets('/project/src'))->toHaveLength(0);
        });

        it('omits a class whose source file no longer exists', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl'));
            allow($fs->read())->toReturn('{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Extract Method","description":"x"}' . "\n");

            expect((new RefactorJournal($fs))->unchangedTargets('/project/src'))->toHaveLength(0);
        });

        it('judges each class by its own refactoring, not by the latest overall', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl') || str_starts_with($p, '/project/src/App/'));
            allow($fs->read())->toReturn(
                '{"at":1000,"command":"refactor","target":"App\\\\Changed","technique":"Rename","description":"x"}' . "\n"
                . '{"at":2000,"command":"refactor","target":"App\\\\Polished","technique":"Extract Method","description":"y"}' . "\n",
            );
            allow($fs->mtime())->toReturn(1500);

            expect((new RefactorJournal($fs))->unchangedTargets('/project/src'))->toBe(['App\\Polished']);
        });

        it('uses the latest entry when a class was refactored more than once', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl') || $p === '/project/src/App/TodoList.php');
            allow($fs->read())->toReturn(
                '{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Extract Method","description":"x"}' . "\n"
                . '{"at":2000,"command":"refactor","target":"App\\\\TodoList","technique":"Inline Method","description":"y"}' . "\n",
            );
            allow($fs->mtime())->toReturn(1500);

            expect((new RefactorJournal($fs))->unchangedTargets('/project/src'))->toBe(['App\\TodoList']);
        });

    });

    it('renders the recent entries for a prompt', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl'));
        allow($fs->read())->toReturn('{"at":100,"command":"refactor","target":"App\\\\TodoList","technique":"Extract Method","description":"Pulled hasTask out"}' . "\n");

        $rendered = (new RefactorJournal($fs))->rendered();

        expect($rendered)->toContain('Extract Method');
        expect($rendered)->toContain('App\\TodoList');
        expect($rendered)->toContain('Pulled hasTask out');
    });

});
