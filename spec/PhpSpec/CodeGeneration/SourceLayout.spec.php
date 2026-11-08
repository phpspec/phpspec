<?php

use PhpSpec\CodeGeneration\SourceLayout;

describe(SourceLayout::class, function () {

    $path = fn(string ...$parts): string => str_replace('/', DIRECTORY_SEPARATOR, implode('/', $parts)) . '.php';

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

    it('files a class under no namespace mapping in the directory a mapping of "" names, its whole name as directories', function () use ($path) {
        $layout = new SourceLayout('src', ['Brew\\' => 'src/Brew', '' => 'lib/']);

        expect($layout->mappings())->toBe(['Brew' => 'src/Brew']);
        expect($layout->isMapped('Acme\\Thing'))->toBeFalse();
        expect($layout->covers('Acme\\Thing'))->toBeTrue();
        expect($layout->relativeFileFor('Acme\\Thing'))->toBe($path('lib', 'Acme', 'Thing'));
        expect($layout->classAt('lib/Acme/Thing.php'))->toBe('Acme\\Thing');
        expect($layout->directories())->toBe(['src', 'src/Brew', 'lib']);
        expect($layout->holds('lib/Acme/Thing.php'))->toBeTrue();
        expect((new SourceLayout('src', ['Brew\\' => 'src/Brew']))->covers('Acme\\Thing'))->toBeFalse();
        expect((new SourceLayout('src', ['Brew\\' => 'src/Brew']))->covers('Brew\\Thing'))->toBeTrue();
    });

    it('names the directories to walk for its classes once each, none inside another', function () {
        expect((new SourceLayout('src', ['App\\' => 'src/', 'Legacy\\' => 'src/legacy', 'Lib\\' => 'lib', 'Srcery\\' => 'srcery', '' => 'src']))->roots())->toBe(['src', 'lib', 'srcery']);
        expect((new SourceLayout('src', ['App\\' => 'app']))->roots())->toBe(['src', 'app']);
    });

    it('lets the longest matching prefix win when mappings nest', function () use ($path) {
        $layout = new SourceLayout('src', ['App\\' => 'src/', 'App\\Legacy\\' => 'legacy/']);

        expect($layout->relativeFileFor('App\\Legacy\\Order'))->toBe($path('legacy', 'Order'));
        expect($layout->relativeFileFor('App\\Order'))->toBe($path('src', 'Order'));
        expect($layout->relativeFileFor('AppLegacy\\Order'))->toBe($path('src', 'AppLegacy', 'Order'));
    });

    it('names the class a file holds by where it lies: the longest mapped directory gives its prefix, the source path its whole name', function () {
        $layout = new SourceLayout('lib', ['App\\' => 'src/', 'Legacy\\' => 'src/legacy']);

        expect($layout->classAt('src/Order.php'))->toBe('App\\Order');
        expect($layout->classAt('src/legacy/Order.php'))->toBe('Legacy\\Order');
        expect($layout->classAt('src\\Model\\User.php'))->toBe('App\\Model\\User');
        expect($layout->classAt('lib/Acme/Thing.php'))->toBe('Acme\\Thing');
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

    it('holds the files under its source path and every mapped directory, and no other', function () {
        $layout = new SourceLayout('/app/src', ['Shop\\' => '/app/modules/shop']);

        expect($layout->holds('/app/src/Basket.php'))->toBeTrue();
        expect($layout->holds('/app/modules/shop/Till.php'))->toBeTrue();
        expect($layout->holds('/app/vendor/acme/Store.php'))->toBeFalse();
    });
});
