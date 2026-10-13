<?php

use PhpSpec\CodeGeneration\FeatureLayout;
use PhpSpec\Filesystem;

describe(FeatureLayout::class, function () {
    this('layout', fn() => new FeatureLayout());

    it('finds the features under features/scenarios when that directory exists, and the steps under features/steps', function (Filesystem $fs) {
        $base = getcwd() . '/features';
        allow($fs->exists(any()))->toReturnUsing(fn(string $p): bool => $p === $base);
        allow($fs->isDir(any()))->toReturnUsing(fn(string $p): bool => $p === $base);
        allow($fs->scandir(any()))->toReturn(['scenarios', 'steps']);

        expect($this->layout->roots($fs))->toBe(['features' => 'features/scenarios', 'steps' => 'features/steps']);
    });

    it('finds the features in features itself when it has no scenarios directory', function (Filesystem $fs) {
        $base = getcwd() . '/features';
        allow($fs->exists(any()))->toReturnUsing(fn(string $p): bool => $p === $base);
        allow($fs->isDir(any()))->toReturnUsing(fn(string $p): bool => $p === $base);
        allow($fs->scandir(any()))->toReturn(['adding.feature', 'steps']);

        expect($this->layout->roots($fs)['features'])->toBe('features');
    });
});
