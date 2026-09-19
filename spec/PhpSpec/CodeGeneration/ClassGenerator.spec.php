<?php

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\Filesystem;

describe(ClassGenerator::class, function () {

    let('generator', fn(Filesystem $fs) => new ClassGenerator('src', $fs));

    it("instantiates", function () {
        expect($this->generator)->toBeAnInstanceOf(ClassGenerator::class);
    });

    it("writes class file when it does not exist", function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        $result = $this->generator->generate('Acme\\Foo');

        expect($fs->mkdir())->toBeCalled();
        expect($fs->write())->toBeCalled();
        expect($result)->toBe("Class Acme\\Foo generated in src/Acme/Foo.php");
    });

    it("ends the file with a newline", function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        $this->generator->generate('Acme\\Foo');

        expect($fs->write(any(), satisfy(fn (string $content) => str_ends_with($content, "}\n"))))->toBeCalled();
    });

    it("throws when class file already exists", function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);

        expect(fn() => $this->generator->generate('Existing'))->toThrow(\RuntimeException::class);
    });

    it("skips mkdir when directory already exists", function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(function (string $path) {
            // File doesn't exist, but directory does
            return str_ends_with($path, '.php') ? false : true;
        });

        $this->generator->generate('Bar');

        expect($fs->mkdir())->not()->toBeCalled();
        expect($fs->write())->toBeCalled();
    });

    it("handles namespaced classes with nested directories", function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        $result = $this->generator->generate('App\\Models\\User');

        $nested = implode(DIRECTORY_SEPARATOR, ['App', 'Models', 'User.php']);
        expect($fs->write(satisfy(fn (string $path) => str_ends_with($path, $nested)), any()))->toBeCalled();
        expect($result)->toContain("Class App\\Models\\User generated in");
    });

    it("strips PSR-4 prefix from directory path", function () {
        $resolved = ClassGenerator::resolveFqcn('App\\Model\\User', 'src', 'App');
        // With prefix "App", App\Model\User -> src/Model/User.php (not src/App/Model/User.php)
        expect($resolved['filePath'])->toContain('src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'User.php');
        expect($resolved['filePath'])->not()->toContain('src' . DIRECTORY_SEPARATOR . 'App');
    });

    it("resolves without PSR-4 prefix when prefix is empty", function () {
        $resolved = ClassGenerator::resolveFqcn('App\\Model\\User', 'src', '');
        expect($resolved['filePath'])->toContain('src' . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Model');
    });

    it("strips multi-level PSR-4 prefix", function () {
        $resolved = ClassGenerator::resolveFqcn('Acme\\Bundle\\Entity\\User', 'src', 'Acme\\Bundle');
        expect($resolved['filePath'])->toContain('src' . DIRECTORY_SEPARATOR . 'Entity' . DIRECTORY_SEPARATOR . 'User.php');
        expect($resolved['filePath'])->not()->toContain('Acme');
    });

});
