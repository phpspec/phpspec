<?php

use PhpSpec\Filesystem;
use PhpSpec\Source\ComposerAutoload;

describe(ComposerAutoload::class, function () {
    $composerJson = function (Filesystem $fs, string $content): void {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/composer.json');
        allow($fs->read(any()))->toReturn($content);
    };

    it('is none for a project with no composer.json', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        expect(ComposerAutoload::of('/app', $fs))->toBeNull();
    });

    it('maps each namespace composer.json maps to the first directory it lists, as the project root names it', function (Filesystem $fs) use ($composerJson) {
        $composerJson($fs, json_encode(['autoload' => ['psr-4' => ['Roastery\\' => './src/', 'Tools\\' => ['tools/', 'lib/tools']]]]));

        expect(ComposerAutoload::of('/app', $fs)?->mappings())->toBe(['Roastery\\' => 'src', 'Tools\\' => 'tools']);
    });

    it('names every directory composer.json maps a namespace to, and tells whether it maps one to a directory however it is written', function (Filesystem $fs) use ($composerJson) {
        $composerJson($fs, json_encode(['autoload' => ['psr-4' => ['Roastery\\' => './src/', 'Tools\\' => ['tools/', 'lib/tools']]]]));
        $autoload = ComposerAutoload::of('/app', $fs);

        expect($autoload?->directories())->toBe(['src', 'tools', 'lib/tools']);
        expect($autoload?->mapsTo('src'))->toBeTrue();
        expect($autoload?->mapsTo('./lib/tools/'))->toBeTrue();
        expect($autoload?->mapsTo('lib'))->toBeFalse();
    });

    it('names a namespace it maps into a directory anywhere but at that namespace\'s whole name there', function () {
        expect((new ComposerAutoload(['Other\\' => ['src/'], '' => ['src/']]))->claimOn('src'))->toBe('Composer maps src/ to Other\\');
        expect((new ComposerAutoload(['Kit\\' => ['lib/', 'src/Kitchen']]))->claimOn('./src/'))->toBe('Composer maps src/Kitchen/ to Kit\\');
        expect((new ComposerAutoload(['Brew\\' => ['src/Brew/'], 'Brew\\Acme\\' => ['src/Brew/Acme'], '' => ['src/']]))->claimOn('src'))->toBeNull();
        expect((new ComposerAutoload(['Tools\\' => ['tools/', 'srcery/']]))->claimOn('src'))->toBeNull();
    });

    it('tells whether it places a namespace: under one of its prefixes, or anywhere once it maps ""', function () {
        $autoload = new ComposerAutoload(['Roastery\\' => ['src/']]);

        expect($autoload->places('Roastery'))->toBeTrue();
        expect($autoload->places('Roastery\\Beans'))->toBeTrue();
        expect($autoload->places('Roaster'))->toBeFalse();
        expect($autoload->places('RoasteryLegacy'))->toBeFalse();
        expect((new ComposerAutoload(['Roastery\\' => ['src/'], '' => ['lib/']]))->places('Roaster'))->toBeTrue();
    });

    it('maps nothing for a composer.json without PSR-4 autoloading, or one that does not parse', function (Filesystem $fs) use ($composerJson) {
        foreach ([json_encode(['require' => ['php' => '^8.3']]), '{"autoload": '] as $content) {
            $composerJson($fs, $content);
            $autoload = ComposerAutoload::of('/app', $fs);

            expect($autoload)->not()->toBeNull();
            expect($autoload?->mappings())->toBe([]);
            expect($autoload?->directories())->toBe([]);
        }
    });
});
