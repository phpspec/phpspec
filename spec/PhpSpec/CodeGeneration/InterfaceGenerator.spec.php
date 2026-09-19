<?php

use PhpSpec\CodeGeneration\InterfaceGenerator;
use PhpSpec\Filesystem;

describe(InterfaceGenerator::class, function () {

    let('generator', fn(Filesystem $fs) => new InterfaceGenerator('src', $fs));

    it("instantiates", function () {
        expect($this->generator)->toBeAnInstanceOf(InterfaceGenerator::class);
    });

    it("generates interface file with namespace", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $result = $this->generator->generate('Acme\\Math\\Calculator');

        expect($fs->mkdir())->toBeCalled();
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'namespace Acme\\Math;'))))->toBeCalled();
        expect($result)->toBe("Interface Acme\\Math\\Calculator generated in src/Acme/Math/Calculator.php");
    });

    it("generates interface file without namespace", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $result = $this->generator->generate('Calculator');

        expect($fs->write(any(), satisfy(fn (string $content) => !str_contains($content, 'namespace'))))->toBeCalled();
        expect($result)->toBe("Interface Calculator generated in src/Calculator.php");
    });

    it("ends the file with a newline", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $this->generator->generate('Calculator');

        expect($fs->write(any(), satisfy(fn (string $content) => str_ends_with($content, "}\n"))))->toBeCalled();
    });

    it("creates directory if missing", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $this->generator->generate('App\\Services\\Mailer');

        expect($fs->mkdir())->toBeCalled();
    });

    it("throws when interface file already exists", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);

        expect(fn() => $this->generator->generate('Existing'))->toThrow(\RuntimeException::class);
    });

    it("skips mkdir when directory already exists", function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(function (string $path) {
            return str_ends_with($path, '.php') ? false : true;
        });

        $this->generator->generate('Bar');

        expect($fs->mkdir())->not()->toBeCalled();
        expect($fs->write())->toBeCalled();
    });

});
