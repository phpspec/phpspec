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

        expect($fs->mkdir(''))->toBeCalled();
        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Class 'Foo' generated");
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

        expect($fs->mkdir(''))->not()->toBeCalled();
        expect($fs->write('', ''))->toBeCalled();
    });

    it("handles namespaced classes with nested directories", function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        $result = $this->generator->generate('App\\Models\\User');

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Class 'User' generated");
    });

});
