<?php

use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Filesystem;

describe(ClassLocation::class, function () {

    it('resolves the file path from the FQCN, src path and PSR-4 prefix', function () {
        $location = ClassLocation::for('App\\Model\\User', SourceLayout::under('src', 'App'));

        expect($location->filePath())->toBe(
            getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'User.php',
        );
    });

    it('reports existence via the filesystem at the resolved path', function (Filesystem $fs) {
        $location = ClassLocation::for('App\\Wallet', SourceLayout::under('src', 'App'));

        allow($fs->exists($location->filePath()))->toReturn(true);

        expect($location->exists($fs))->toBe(true);
    });

    it('locates a loaded class by the file it was loaded from, whatever the layout says', function () {
        $location = ClassLocation::for('PhpSpec\\CodeGeneration\\ClassGenerator', SourceLayout::under('lib', 'Acme'));

        expect($location->filePath())->toBe(realpath(__DIR__ . '/../../../src/PhpSpec/CodeGeneration/ClassGenerator.php'));
    });

    it('keeps a vendor class where the layout would put it, never in vendor', function () {
        $location = ClassLocation::for('Symfony\\Component\\Console\\Application', SourceLayout::under('src'));

        expect($location->filePath())->toBe(
            getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Symfony' . DIRECTORY_SEPARATOR . 'Component' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'Application.php',
        );
    });

    it('keeps an internal class where the layout would put it', function () {
        expect(ClassLocation::for('ArrayObject', SourceLayout::under('src'))->filePath())->toBe(getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ArrayObject.php');
    });

    it('is autoloadable when the class actually exists', function () {
        $location = ClassLocation::for('PhpSpec\\CodeGeneration\\ClassGenerator', SourceLayout::under('src', 'PhpSpec'));

        expect($location->isAutoloadable())->toBe(true);
    });

    it('is not autoloadable for a class that does not exist', function () {
        expect(ClassLocation::for('App\\NotARealClass', SourceLayout::under('src', 'App'))->isAutoloadable())->toBe(false);
    });

});
