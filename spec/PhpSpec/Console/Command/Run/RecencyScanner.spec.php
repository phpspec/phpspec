<?php

use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;

describe(RecencyScanner::class, function () {

    it('returns the most recently modified .feature file', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === 'features');
        allow($fs->scandir())->toReturn(['.', '..', 'a.feature', 'b.feature', 'notes.txt']);
        allow($fs->mtime())->toReturnUsing(fn(string $p): int => match ($p) {
            'features/a.feature' => 100,
            'features/b.feature' => 200,
            default => 0,
        });

        $scanner = new RecencyScanner($fs);

        expect($scanner->mostRecentFeature('features'))->toBe('features/b.feature');
    });

    it('finds the most recent .feature in nested subdirectories', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, ['features', 'features/cli'], true));
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
            'features' => ['.', '..', 'top.feature', 'cli'],
            'features/cli' => ['.', '..', 'deep.feature'],
            default => [],
        });
        allow($fs->mtime())->toReturnUsing(fn(string $p): int => match ($p) {
            'features/top.feature' => 50,
            'features/cli/deep.feature' => 500,
            default => 0,
        });

        $scanner = new RecencyScanner($fs);

        expect($scanner->mostRecentFeature('features'))->toBe('features/cli/deep.feature');
    });

    it('returns null when the features directory does not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $scanner = new RecencyScanner($fs);

        expect($scanner->mostRecentFeature('features'))->toBeNull();
    });

    it('returns null when no .feature files are present', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === 'features');
        allow($fs->scandir())->toReturn(['.', '..', 'readme.md']);

        $scanner = new RecencyScanner($fs);

        expect($scanner->mostRecentFeature('features'))->toBeNull();
    });

    it('returns the most recently modified source file, recursing into subdirectories', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === 'src' || $p === 'src/App');
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
            'src' => ['.', '..', 'App'],
            'src/App' => ['.', '..', 'Calculator.php', 'Basket.php'],
            default => [],
        });
        allow($fs->mtime())->toReturnUsing(fn(string $p): int => match ($p) {
            'src/App/Calculator.php' => 10,
            'src/App/Basket.php' => 20,
            default => 0,
        });

        $scanner = new RecencyScanner($fs);

        expect($scanner->mostRecentSource('src'))->toBe('src/App/Basket.php');
    });

});
