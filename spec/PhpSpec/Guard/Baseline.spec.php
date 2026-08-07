<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Baseline;
use PhpSpec\Guard\CannotJudge;
use PhpSpec\Guard\Git;

describe(Baseline::class, function () {

    // Committing is a deliberate act of acceptance, so in a repository the
    // commit is where the session starts.
    it('records the commit when the project is a repository', function (Filesystem $fs, Git $git) {
        $written = null;
        allow($git->isRepository())->toReturn(true);
        allow($git->head())->toReturn('9f2c1ab3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9');
        allow($fs->exists())->toReturn(true);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = ['path' => $path, 'content' => $content];
        });

        $recorded = (new Baseline($fs, $git, '/app'))->record(['src']);

        expect($recorded)->toBe(['kind' => 'commit', 'commit' => '9f2c1ab3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9']);
        expect($written['path'])->toBe('/app/.phpspec/guard/baseline.json');
        expect(json_decode((string) $written['content'], true))->toBe(['recorded' => $recorded]);
    });

    // Without a repository there is nothing to point at, so guard remembers
    // what each guarded file held instead.
    it('records what the guarded files hold when there is no repository', function (Filesystem $fs, Git $git) {
        allow($git->isRepository())->toReturn(false);
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturnUsing(fn(string $path) => $path === '/app/src');
        allow($fs->scandir())->toReturn(['.', '..', 'Basket.php', 'notes.txt']);
        allow($fs->read())->toReturn('<?php class Basket {}');
        allow($fs->write())->toReturn(null);

        $recorded = (new Baseline($fs, $git, '/app'))->record(['src']);

        expect($recorded['kind'])->toBe('snapshot');
        // What it held, not a hash of it: without the content there is no
        // line-level verdict later, only "this file changed".
        expect($recorded['files'])->toBe(['src/Basket.php' => '<?php class Basket {}']);
    });

    // A repository with no commits yet has nothing to be a baseline, so it is
    // treated as no repository at all rather than failing.
    it('falls back to a snapshot when the repository has no commit yet', function (Filesystem $fs, Git $git) {
        allow($git->isRepository())->toReturn(true);
        allow($git->head())->toReturn(null);
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturn(false);
        allow($fs->write())->toReturn(null);

        expect((new Baseline($fs, $git, '/app'))->record(['src'])['kind'])->toBe('snapshot');
    });

    it('makes the directory it keeps the baseline in', function (Filesystem $fs, Git $git) {
        $made = null;
        allow($git->isRepository())->toReturn(true);
        allow($git->head())->toReturn('abc123');
        allow($fs->exists())->toReturn(false);
        allow($fs->write())->toReturn(null);
        allow($fs->mkdir())->toReturnUsing(function (string $path) use (&$made) {
            $made = $path;
        });

        (new Baseline($fs, $git, '/app'))->record(['src']);

        expect($made)->toBe('/app/.phpspec/guard');
    });

    // A project that asked for mtime detection said which reader it wants.
    // Recording a commit anyway hands that reader a baseline it cannot read,
    // and every guarded file then looks new.
    it('records what the project asked for, even where git could have answered', function (Filesystem $fs, Git $git) {
        allow($git->isRepository())->toReturn(true);
        allow($git->head())->toReturn('abc123');
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturn(false);
        allow($fs->write())->toReturn(null);

        expect((new Baseline($fs, $git, '/app'))->record(['src'], 'mtime')['kind'])->toBe('snapshot');
    });

    it('reads back what was recorded', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('{"recorded":{"kind":"commit","commit":"abc123"}}');

        expect((new Baseline($fs, $git, '/app'))->recorded())->toBe(['kind' => 'commit', 'commit' => 'abc123']);
    });

    // The state of every fresh clone, because the baseline is local by design.
    // Guard has to say so: a silent pass here leaves a whole team believing
    // they are guarded when nobody is.
    it('refuses to judge when guard was never turned on here', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(false);

        expect(fn() => (new Baseline($fs, $git, '/app'))->recorded())
            ->toThrow(CannotJudge::class, 'Guard is on but no baseline is recorded in this checkout: run "bin/phpspec guard" to judge from here.');
    });

    it('refuses to judge on a baseline it cannot read', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('{ this is not the file we wrote');

        expect(fn() => (new Baseline($fs, $git, '/app'))->recorded())
            ->toThrow(CannotJudge::class, 'Guard cannot read the baseline in .phpspec/guard/baseline.json: run "bin/phpspec guard" to record it again.');
    });

});
