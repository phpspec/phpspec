<?php

use PhpSpec\CodeGeneration\MethodStubGenerator;
use PhpSpec\Filesystem;

describe(MethodStubGenerator::class, function () {

    let('generator', fn(Filesystem $fs) => new MethodStubGenerator('src', $fs));

    it("instantiates", function () {
        expect($this->generator)->toBeAnInstanceOf(MethodStubGenerator::class);
    });

    it("generates a method stub with no arguments", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");

        $result = $this->generator->generate('Calculator', 'add', 0);

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Method 'add()' generated");
    });

    it("generates a method stub with arguments", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");

        $result = $this->generator->generate('Calculator', 'add', 2);

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Method 'add()' generated");
    });

    it("throws when source file does not exist", function () {
        expect(fn() => $this->generator->generate('Missing', 'foo'))
            ->toThrow(\RuntimeException::class);
    });

    it("throws when method already exists", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n    public function add() {}\n}\n");

        expect(fn() => $this->generator->generate('Calculator', 'add'))
            ->toThrow(\RuntimeException::class);
    });

    it("resolves namespaced class to correct file path", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nnamespace Acme\\Math;\n\nclass Calculator\n{\n}\n");

        $result = $this->generator->generate('Acme\\Math\\Calculator', 'add', 3);

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Method 'add()' generated");
    });

    it("generates interface method stub without body", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\ninterface Calculator\n{\n}\n");

        $result = $this->generator->generate('Calculator', 'add', 2);

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Method 'add()' generated");
    });

    it("generates method with hardcoded return value when given", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");

        $result = $this->generator->generate('Calculator', 'add', 2, '42');

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Method 'add()' generated");
    });

    it("fills empty method body with return value", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n    public function add(\$a, \$b)\n    {\n    }\n}\n");

        $result = $this->generator->fillEmptyMethod('Calculator', 'add', '42');

        expect($fs->write('', ''))->toBeCalled();
        expect($result)->toContain("Method 'add()' filled");
    });

    it("detects empty method body", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n    public function add(\$a, \$b)\n    {\n    }\n}\n");

        expect($this->generator->hasEmptyMethod('Calculator', 'add'))->toBeTrue();
    });

    it("reports non-empty method as not empty", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n    public function add(\$a, \$b)\n    {\n        return \$a + \$b;\n    }\n}\n");

        expect($this->generator->hasEmptyMethod('Calculator', 'add'))->toBeFalse();
    });

    it("throws when closing brace not found", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n");

        expect(fn() => $this->generator->generate('Calculator', 'add'))
            ->toThrow(\RuntimeException::class);
    });

    it("throws from fillEmptyMethod when source file does not exist", function () {
        expect(fn() => $this->generator->fillEmptyMethod('Missing', 'foo', '42'))
            ->toThrow(\RuntimeException::class);
    });

    it("throws from fillEmptyMethod when method body is not empty", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n    public function add(\$a, \$b)\n    {\n        return \$a + \$b;\n    }\n}\n");

        expect(fn() => $this->generator->fillEmptyMethod('Calculator', 'add', '99'))
            ->toThrow(\RuntimeException::class);
    });

    it("returns false from hasEmptyMethod when file does not exist", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect($this->generator->hasEmptyMethod('Missing', 'foo'))->toBeFalse();
    });

});
