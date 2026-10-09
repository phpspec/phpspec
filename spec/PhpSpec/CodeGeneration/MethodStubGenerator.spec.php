<?php

use PhpSpec\CodeGeneration\MethodStubGenerator;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Filesystem;

describe(MethodStubGenerator::class, function () {

    let('generator', fn(Filesystem $fs) => new MethodStubGenerator(SourceLayout::under('src'), $fs));

    it("instantiates", function () {
        expect($this->generator)->toBeAnInstanceOf(MethodStubGenerator::class);
    });

    it("generates a method stub with no arguments", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, "public function add()\n    {\n    }"))))->toBeCalled();

        $result = $this->generator->generate('Calculator', 'add', 0);

        expect($result)->toContain("Method add() generated");
    });

    it("generates a method stub with arguments", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'public function add($argument1, $argument2)'))))->toBeCalled();

        $result = $this->generator->generate('Calculator', 'add', 2);

        expect($result)->toContain("Method add() generated");
    });

    it("names the file relative to the project in its receipt, as the class receipt does", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");
        allow($fs->write());

        expect($this->generator->generate('Calculator', 'add', 0))->toBe('Method add() generated in src/Calculator.php');
    });

    it("throws when source file does not exist", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

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
        $nested = implode(DIRECTORY_SEPARATOR, ['Acme', 'Math', 'Calculator.php']);
        expect($fs->write(satisfy(fn (string $path) => str_ends_with($path, $nested)), any()))->toBeCalled();

        $result = $this->generator->generate('Acme\\Math\\Calculator', 'add', 3);

        expect($result)->toContain("Method add() generated");
    });

    it("writes into the file a loaded class came from when the layout says otherwise", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nnamespace PhpSpec\\CodeGeneration;\n\nclass ClassGenerator\n{\n}\n");
        $loadedFrom = realpath(__DIR__ . '/../../../src/PhpSpec/CodeGeneration/ClassGenerator.php');
        expect($fs->write($loadedFrom, any()))->toBeCalled();

        (new MethodStubGenerator(SourceLayout::under('lib', 'Acme'), $fs))->generate('PhpSpec\\CodeGeneration\\ClassGenerator', 'spin', 0);
    });

    it("puts the first method right under the opening brace, and a later one a line apart", function (Filesystem $fs) {
        $content = "<?php\n\nnamespace App;\n\ninterface Catalogue\n{\n}\n";
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(function () use (&$content): string { return $content; });
        allow($fs->write())->toReturnUsing(function (string $path, string $written) use (&$content): void { $content = $written; });
        $generator = new MethodStubGenerator(filesystem: $fs);

        $generator->generate('App\\Catalogue', 'priceOf', 1);
        expect($content)->toEndWith("interface Catalogue\n{\n    public function priceOf(\$argument1);\n}\n");

        $generator->generate('App\\Catalogue', 'has', 1);
        expect($content)->toEndWith("interface Catalogue\n{\n    public function priceOf(\$argument1);\n\n    public function has(\$argument1);\n}\n");
    });

    it("generates interface method stub without body", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\ninterface Calculator\n{\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'public function add($argument1, $argument2);'))))->toBeCalled();

        $result = $this->generator->generate('Calculator', 'add', 2);

        expect($result)->toContain("Method add() generated");
    });

    it("generates a static method stub when the method was called statically", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass TaskList\n{\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, "public static function of(\$argument1, \$argument2)\n    {\n    }"))))->toBeCalled();

        $this->generator->generate('TaskList', 'of', 2, static: true);
    });

    it("generates a static interface method stub when the method was called statically", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\ninterface TaskList\n{\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'public static function of($argument1);'))))->toBeCalled();

        $this->generator->generate('TaskList', 'of', 1, static: true);
    });

    it("generates method with hardcoded return value when given", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'return 42;'))))->toBeCalled();

        $result = $this->generator->generate('Calculator', 'add', 2, '42');

        expect($result)->toContain("Method add() generated");
    });

    it("fills empty method body with return value", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\nclass Calculator\n{\n    public function add(\$a, \$b)\n    {\n    }\n}\n");
        expect($fs->write(any(), satisfy(fn (string $content) => str_contains($content, 'return 42;'))))->toBeCalled();

        $result = $this->generator->fillEmptyMethod('Calculator', 'add', '42');

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

    it("throws from fillEmptyMethod when source file does not exist", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

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
