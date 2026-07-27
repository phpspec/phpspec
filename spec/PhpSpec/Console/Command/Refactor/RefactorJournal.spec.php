<?php

use PhpSpec\Console\Command\Refactor\RefactorJournal;
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

    it('reports the last refactoring timestamp, and null for an empty journal', function (Filesystem $fs) {
        expect((new RefactorJournal($fs))->lastRefactoringAt())->toBeNull();

        allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_contains($p, 'journal.jsonl'));
        allow($fs->read())->toReturn(
            '{"at":100,"command":"refactor","target":"A","technique":"Rename","description":"x"}' . "\n"
            . '{"at":200,"command":"refactor","target":"B","technique":"Extract Method","description":"y"}' . "\n",
        );

        expect((new RefactorJournal($fs))->lastRefactoringAt())->toBe(200);
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
