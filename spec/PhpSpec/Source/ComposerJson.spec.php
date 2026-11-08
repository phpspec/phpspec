<?php

use PhpSpec\Filesystem;
use PhpSpec\Source\ComposerJson;

describe(ComposerJson::class, function () {
    // A composer.json on a fake filesystem that keeps whatever is written to it.
    $composerJson = function (Filesystem $fs, string $content): ArrayObject {
        $files = new ArrayObject(['/app/composer.json' => $content]);
        allow($fs->exists(any()))->toReturnUsing(fn(string $path): bool => isset($files[$path]));
        allow($fs->read(any()))->toReturnUsing(fn(string $path): string => $files[$path]);
        allow($fs->write(cetera()))->toReturnUsing(function (string $path, string $content) use ($files): void {
            $files[$path] = $content;
        });

        return $files;
    };

    it('maps the classes below a directory by their whole name in a composer.json that autoloads none, written as it was, and says so', function (Filesystem $fs) use ($composerJson) {
        $files = $composerJson($fs, "{\n  \"name\": \"acme/kata\",\n  \"require\": {},\n  \"keywords\": [],\n  \"autoload\": {\n    \"files\": [\"helpers.php\"]\n  }\n}\n");

        expect((new ComposerJson('/app/composer.json', $fs))->autoloadBelow('src'))->toBe('Composer autoload was updated to include "": "src/". Run composer dumpautoload next.');
        expect($files['/app/composer.json'])->toBe("{\n  \"name\": \"acme/kata\",\n  \"require\": {},\n  \"keywords\": [],\n  \"autoload\": {\n    \"files\": [\n      \"helpers.php\"\n    ],\n    \"psr-4\": {\n      \"\": \"src/\"\n    }\n  }\n}\n");
    });

    it('adds the autoload section a composer.json lacks, indented as Composer writes it', function (Filesystem $fs) use ($composerJson) {
        $files = $composerJson($fs, '{"name": "acme/kata"}');

        expect((new ComposerJson('/app/composer.json', $fs))->autoloadBelow('lib/'))->toBe('Composer autoload was updated to include "": "lib/". Run composer dumpautoload next.');
        expect($files['/app/composer.json'])->toBe("{\n    \"name\": \"acme/kata\",\n    \"autoload\": {\n        \"psr-4\": {\n            \"\": \"lib/\"\n        }\n    }\n}");
    });

    it('adds the mapping beside the namespaces composer.json maps already', function (Filesystem $fs) use ($composerJson) {
        $files = $composerJson($fs, '{"autoload": {"psr-4": {"Brew\\\\": "src/Brew/"}}}');

        expect((new ComposerJson('/app/composer.json', $fs))->autoloadBelow('src'))->toBe('Composer autoload was updated to include "": "src/". Run composer dumpautoload next.');
        expect($files['/app/composer.json'])->toBe("{\n    \"autoload\": {\n        \"psr-4\": {\n            \"Brew\\\\\": \"src/Brew/\",\n            \"\": \"src/\"\n        }\n    }\n}");
    });

    it('leaves a composer.json that maps every namespace already, autoloads by rules it does not follow, or does not parse, as it is', function (Filesystem $fs) use ($composerJson) {
        foreach ([
            '{"autoload": {"psr-4": {"": "lib/"}}}',
            '{"autoload": {"psr-0": {"App": "src/"}}}',
            '{"autoload": {"classmap": ["src/"]}}',
            '{"autoload": ',
        ] as $content) {
            $files = $composerJson($fs, $content);

            expect((new ComposerJson('/app/composer.json', $fs))->autoloadBelow('src'))->toBeNull();
            expect($files['/app/composer.json'])->toBe($content);
        }
    });

    it('says why a class cannot be written below a directory composer.json maps to another namespace', function (Filesystem $fs) use ($composerJson) {
        $composerJson($fs, '{"autoload": {"psr-4": {"Other\\\\": "src/"}}}');
        $composer = new ComposerJson('/app/composer.json', $fs);

        expect($composer->refusal('Brew\\Acme\\Sombrero', 'src'))->toBe('Brew\\Acme\\Sombrero cannot be written under src/: Composer maps src/ to Other\\.');
        expect($composer->refusal('Brew\\Acme\\Sombrero', 'lib'))->toBeNull();
    });

    it('leaves composer.json as it is for classes at the project root', function (Filesystem $fs) use ($composerJson) {
        $files = $composerJson($fs, '{"name": "acme/kata"}');

        expect((new ComposerJson('/app/composer.json', $fs))->autoloadBelow(''))->toBeNull();
        expect($files['/app/composer.json'])->toBe('{"name": "acme/kata"}');
    });
});
