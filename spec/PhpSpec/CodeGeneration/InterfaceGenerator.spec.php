<?php

use PhpSpec\CodeGeneration\InterfaceGenerator;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Filesystem;

describe(InterfaceGenerator::class, function () {

    let('generator', fn(Filesystem $fs) => new InterfaceGenerator(SourceLayout::under('src'), $fs));

    it("instantiates", function () {
        expect($this->generator)->toBeAnInstanceOf(InterfaceGenerator::class);
    });

    it("generates interface file with namespace", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        expect($fs->mkdir())->toBeCalled();
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'namespace Acme\\Math;'))))->toBeCalled();

        $result = $this->generator->generate('Acme\\Math\\Calculator');

        expect($result)->toBe("Interface Acme\\Math\\Calculator generated in src/Acme/Math/Calculator.php");
    });

    it("leaves no empty line inside an empty body", function (Filesystem $fs) {
        $written = '';
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        (new InterfaceGenerator(filesystem: $fs))->generate('App\\Catalogue');

        expect(str_replace("\r\n", "\n", $written))->toEndWith("interface Catalogue\n{\n}\n");
    });

    it("generates interface file without namespace", function (Filesystem $fs) {
        allow($fs->mkdir());
        allow($fs->exists())->toReturn(false);
        expect($fs->write(any(), satisfy(fn (string $content) => !str_contains($content, 'namespace'))))->toBeCalled();

        $result = $this->generator->generate('Calculator');

        expect($result)->toBe("Interface Calculator generated in src/Calculator.php");
    });

    it("ends the file with a newline", function (Filesystem $fs) {
        allow($fs->mkdir());
        allow($fs->exists())->toReturn(false);
        expect($fs->write(any(), satisfy(fn (string $content) => str_ends_with($content, "\n"))))->toBeCalled();

        $this->generator->generate('Calculator');
    });

    it("creates directory if missing", function (Filesystem $fs) {
        allow($fs->write());
        allow($fs->exists())->toReturn(false);
        expect($fs->mkdir())->toBeCalled();

        $this->generator->generate('App\\Services\\Mailer');
    });

    it("throws when interface file already exists", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);

        expect(fn() => $this->generator->generate('Existing'))->toThrow(\RuntimeException::class);
    });

    it("skips mkdir when directory already exists", function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(function (string $path) {
            return str_ends_with($path, '.php') ? false : true;
        });
        expect($fs->write())->toBeCalled();

        $this->generator->generate('Bar');

        expect($fs->mkdir())->not()->toBeCalled();
    });

});
