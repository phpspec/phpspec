<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Git;
use PhpSpec\Guard\GitDelta;
use PhpSpec\Guard\Scope;

describe(GitDelta::class, function () {

    $diff = <<<DIFF
    --- a/src/App/Basket.php
    +++ b/src/App/Basket.php
    @@ -10,0 +11,2 @@ class Basket
    +        if (\$this->coupon !== null) {
    +            return 0;
    DIFF;

    it('asks git for the working tree against the baseline commit', function (Git $git, Filesystem $fs) use ($diff) {
        $asked = null;
        allow($git->diff())->toReturnUsing(function (string $commit, array $paths) use (&$asked, $diff) {
            $asked = ['commit' => $commit, 'paths' => $paths];

            return $diff;
        });
        allow($git->untracked())->toReturn([]);

        $delta = (new GitDelta($git, $fs, new Scope(['src']), '/app'))
            ->since(['kind' => 'commit', 'commit' => 'abc123']);

        expect($asked)->toBe(['commit' => 'abc123', 'paths' => ['src']]);
        expect($delta->lines('src/App/Basket.php'))->toBe([11, 12]);
    });

    // A file git has never been told about is new from its first line to its
    // last: there is no earlier version of it to have been reviewed.
    it('counts the whole of a file git has never seen', function (Git $git, Filesystem $fs) {
        allow($git->diff())->toReturn('');
        allow($git->untracked())->toReturn(['src/App/Coupon.php']);
        allow($fs->exists())->toReturn(true);
        allow($fs->readLines())->toReturn(['<?php', 'class Coupon', '{', '}']);

        $delta = (new GitDelta($git, $fs, new Scope(['src']), '/app'))
            ->since(['kind' => 'commit', 'commit' => 'abc123']);

        expect($delta->lines('src/App/Coupon.php'))->toBe([1, 2, 3, 4]);
    });

    it('drops what guard has no opinion about', function (Git $git, Filesystem $fs) {
        allow($git->diff())->toReturn(<<<DIFF
        --- a/spec/App/Basket.spec.php
        +++ b/spec/App/Basket.spec.php
        @@ -1,0 +2,1 @@
        +    it('totals', function () {});
        DIFF);
        allow($git->untracked())->toReturn([]);

        $delta = (new GitDelta($git, $fs, new Scope(['src', 'spec']), '/app'))
            ->since(['kind' => 'commit', 'commit' => 'abc123']);

        expect($delta->isEmpty())->toBeTrue();
    });

    // A snapshot baseline names no commit, so git has nothing to compare with
    // and says so rather than guessing.
    it('has nothing to compare when the baseline is not a commit', function (Git $git, Filesystem $fs) {
        expect((new GitDelta($git, $fs, new Scope(['src']), '/app'))->since(['kind' => 'snapshot'])->isEmpty())->toBeTrue();
    });

});
