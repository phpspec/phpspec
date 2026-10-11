<?php

use PhpSpec\Ai\TreeScanner;
use PhpSpec\Filesystem;

describe(TreeScanner::class, function () {

    it('lists entries sorted, directories marked and their children indented', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        allow($fs->isDir(any()))->toReturnUsing(fn(string $path): bool => !str_ends_with($path, '.php'));
        allow($fs->scandir(any()))->toReturnUsing(fn(string $path): array => str_ends_with($path, '/src')
            ? ['.', '..', 'Model', 'Basket.php']
            : ['User.php']);

        expect((new TreeScanner($fs))->scan('/proj/src', 3))->toBe("Basket.php\nModel/\n  User.php");
    });

    it('stops at the depth limit', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        allow($fs->isDir(any()))->toReturnUsing(fn(string $path): bool => !str_ends_with($path, '.php'));
        allow($fs->scandir(any()))->toReturnUsing(fn(string $path): array => str_ends_with($path, '/src')
            ? ['Model', 'Basket.php']
            : ['User.php']);

        expect((new TreeScanner($fs))->scan('/proj/src', 1))->toBe("Basket.php\nModel/");
    });

    it('returns nothing for a missing or non-directory path', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        expect((new TreeScanner($fs))->scan('/proj/nope', 3))->toBe('');
    });

});
