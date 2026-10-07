<?php

use PhpSpec\CodeGeneration\SourceLayout;

describe(SourceLayout::class, function () {

    $path = fn(string ...$parts): string => implode(DIRECTORY_SEPARATOR, $parts) . '.php';

    it('files a class under the directory its namespace is mapped to, the prefix taken off its name', function () use ($path) {
        $layout = new SourceLayout('src', ['Brew\\' => 'src/Brew/', 'Another\\' => 'src/Another']);

        expect($layout->relativeFileFor('Brew\\Acme\\Thing'))->toBe($path('src/Brew', 'Acme', 'Thing'));
        expect($layout->relativeFileFor('Another\\Thing'))->toBe($path('src/Another', 'Thing'));
    });

    it('files a class under no mapping below the source path, its whole name as directories', function () use ($path) {
        $layout = new SourceLayout('src', ['Brew\\' => 'src/Brew/']);

        expect($layout->isMapped('Acme\\Thing'))->toBeFalse();
        expect($layout->relativeFileFor('Acme\\Thing'))->toBe($path('src', 'Acme', 'Thing'));
        expect($layout->relativeFileFor('Thing'))->toBe($path('src', 'Thing'));
    });

    it('lets the longest matching prefix win when mappings nest', function () use ($path) {
        $layout = new SourceLayout('src', ['App\\' => 'src/', 'App\\Legacy\\' => 'legacy/']);

        expect($layout->relativeFileFor('App\\Legacy\\Order'))->toBe($path('legacy', 'Order'));
        expect($layout->relativeFileFor('App\\Order'))->toBe($path('src', 'Order'));
        expect($layout->relativeFileFor('AppLegacy\\Order'))->toBe($path('src', 'AppLegacy', 'Order'));
    });

    it('stands in for the old source path and prefix pair', function () use ($path) {
        expect(SourceLayout::under('src', 'App')->relativeFileFor('App\\Model\\User'))->toBe($path('src', 'Model', 'User'));
        expect(SourceLayout::under('src')->relativeFileFor('App\\Model\\User'))->toBe($path('src', 'App', 'Model', 'User'));
    });

    it('locates a class as the generators write it: short name, namespace declaration and absolute file', function () use ($path) {
        $located = SourceLayout::under('src', 'App')->locate('App\\Model\\User');

        expect($located['shortName'])->toBe('User');
        expect($located['namespace'])->toBe("\n\nnamespace App\\Model;");
        expect($located['filePath'])->toBe(getcwd() . DIRECTORY_SEPARATOR . $path('src', 'Model', 'User'));
        expect(SourceLayout::under('src')->locate('User')['namespace'])->toBe('');
    });

    it('names every file a class may load from: its mapping, then the whole-name path under the source path', function () use ($path) {
        $layout = new SourceLayout('src', ['Brew\\' => 'lib/Brew']);

        expect($layout->candidateFilesFor('Brew\\Thing'))->toBe([$path('lib/Brew', 'Thing'), $path('src', 'Brew', 'Thing')]);
        expect($layout->candidateFilesFor('Acme\\Thing'))->toBe([$path('src', 'Acme', 'Thing')]);
        expect((new SourceLayout('src', ['Brew\\' => 'src/Brew']))->candidateFilesFor('Brew\\Thing'))->toBe([$path('src/Brew', 'Thing')]);
    });

    it('offers a name under no mapping each mapped namespace it could belong to', function () {
        $layout = new SourceLayout('src', ['Brew\\' => 'src/Brew', 'Another\\' => 'src/Another']);

        expect($layout->candidatesFor('Acme\\Thing'))->toBe(['Brew\\Acme\\Thing', 'Another\\Acme\\Thing']);
    });

    it('puts a name under no mapping into the default namespace, and leaves a mapped one alone', function () {
        $layout = new SourceLayout('src', ['Brew\\Acme\\' => 'src/Brew/Acme', 'Another\\Acme\\' => 'src/Another'], 'Brew\\Acme');

        expect($layout->withinDefaultNamespace('Thing'))->toBe('Brew\\Acme\\Thing');
        expect($layout->withinDefaultNamespace('Another\\Acme\\Thing'))->toBe('Another\\Acme\\Thing');
        expect((new SourceLayout('src', ['Brew\\' => 'src/Brew']))->withinDefaultNamespace('Thing'))->toBe('Thing');
    });
});
