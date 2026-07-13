<?php

use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\Filesystem;

describe(ClassLocation::class, function () {

    it('resolves the file path from the FQCN, src path and PSR-4 prefix', function () {
        $location = ClassLocation::for('App\\Model\\User', 'src', 'App');

        expect($location->filePath())->toBe(
            getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'User.php',
        );
    });

    it('reports existence via the filesystem at the resolved path', function (Filesystem $fs) {
        $location = ClassLocation::for('App\\Wallet', 'src', 'App');

        allow($fs->exists($location->filePath()))->toReturn(true);

        expect($location->exists($fs))->toBe(true);
    });

    it('is autoloadable when the class actually exists', function () {
        $location = ClassLocation::for('PhpSpec\\CodeGeneration\\ClassGenerator', 'src', 'PhpSpec');

        expect($location->isAutoloadable())->toBe(true);
    });

    it('is not autoloadable for a class that does not exist', function () {
        expect(ClassLocation::for('App\\NotARealClass', 'src', 'App')->isAutoloadable())->toBe(false);
    });

});
