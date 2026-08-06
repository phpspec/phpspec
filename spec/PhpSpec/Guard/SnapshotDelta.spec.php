<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Scope;
use PhpSpec\Guard\SnapshotDelta;

describe(SnapshotDelta::class, function () {

    $tree = function (Filesystem $fs, array $files): void {
        allow($fs->isDir())->toReturnUsing(fn(string $path) => $path === '/app/src');
        allow($fs->scandir())->toReturn(['.', '..', ...array_keys($files)]);
        allow($fs->read())->toReturnUsing(fn(string $path) => $files[basename($path)] ?? '');
    };

    it('reports only the lines an edit added', function (Filesystem $fs) use ($tree) {
        $tree($fs, ['Basket.php' => "<?php\nclass Basket\n{\n    public function total() {}\n}"]);

        $delta = (new SnapshotDelta($fs, new Scope(['src']), '/app'))->since([
            'kind' => 'snapshot',
            'files' => ['src/Basket.php' => "<?php\nclass Basket\n{\n}"],
        ]);

        // Only the method is new; the class and its braces were there before.
        expect($delta->lines('src/Basket.php'))->toBe([4]);
    });

    it('counts the whole of a file the baseline never saw', function (Filesystem $fs) use ($tree) {
        $tree($fs, ['Coupon.php' => "<?php\nclass Coupon\n{\n}"]);

        $delta = (new SnapshotDelta($fs, new Scope(['src']), '/app'))->since([
            'kind' => 'snapshot',
            'files' => [],
        ]);

        expect($delta->lines('src/Coupon.php'))->toBe([1, 2, 3, 4]);
    });

    it('has nothing to say about a file nobody touched', function (Filesystem $fs) use ($tree) {
        $tree($fs, ['Basket.php' => "<?php\nclass Basket {}"]);

        $delta = (new SnapshotDelta($fs, new Scope(['src']), '/app'))->since([
            'kind' => 'snapshot',
            'files' => ['src/Basket.php' => "<?php\nclass Basket {}"],
        ]);

        expect($delta->isEmpty())->toBeTrue();
    });

    // Removing code is never new logic left unspecified.
    it('has nothing to say about a change that only removed lines', function (Filesystem $fs) use ($tree) {
        $tree($fs, ['Basket.php' => "<?php\nclass Basket\n{\n}"]);

        $delta = (new SnapshotDelta($fs, new Scope(['src']), '/app'))->since([
            'kind' => 'snapshot',
            'files' => ['src/Basket.php' => "<?php\nclass Basket\n{\n    public function dead() {}\n}"],
        ]);

        expect($delta->isEmpty())->toBeTrue();
    });

});
