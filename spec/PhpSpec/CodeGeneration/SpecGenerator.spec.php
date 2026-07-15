<?php

use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Filesystem;

describe(SpecGenerator::class, function () {

    let('generator', fn(Filesystem $fs) => new SpecGenerator('spec', $fs));

    it("instantiates", function () {
        expect($this->generator)->toBeAnInstanceOf(SpecGenerator::class);
    });

    it("exposes its spec path", function (Filesystem $fs) {
        $generator = new SpecGenerator('custom-specs', $fs);
        expect($generator->getSpecPath())->toBe('custom-specs');
    });

    it("writes spec file when it does not exist", function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match (true) {
            str_ends_with($path, '.spec.php') => false,
            default => true,
        });

        $this->generator->generate('Calculator');

        expect($fs->write('', ''))->toBeCalled();
    });

    it("creates directory when it does not exist", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $this->generator->generate('App/Service');

        expect($fs->mkdir(''))->toBeCalled();
        expect($fs->write('', ''))->toBeCalled();
    });

    it("returns true when it creates the spec file", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect($this->generator->generate('Calculator'))->toBe(true);
    });

    it("returns false when the spec file already exists", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);

        expect($this->generator->generate('Existing'))->toBe(false);
    });

    it("adds example for a method to existing spec", function (Filesystem $fs) {
        $specPath = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'Calculator.spec.php';

        allow($fs->exists($specPath))->toReturn(true);
        allow($fs->read($specPath))->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            let("calculator", fn() => new Calculator());
            it("instantiates", fn() => expect($this->calculator)->toBeAnInstanceOf(Calculator::class));
        });
        PHP);

        $this->generator->addExample('Calculator', 'add');

        expect($fs->write($specPath, ''))->toBeCalled();
    });

    it("includes method name in added example", function (Filesystem $fs) {
        $specPath = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'Calculator.spec.php';
        $written = null;

        allow($fs->exists($specPath))->toReturn(true);
        allow($fs->read($specPath))->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            let("calculator", fn() => new Calculator());
            it("instantiates", fn() => expect($this->calculator)->toBeAnInstanceOf(Calculator::class));
        });
        PHP);

        allow($fs->write())->toReturnUsing(function () use (&$written) {
            $written = func_get_args()[1] ?? null;
        });

        $this->generator->addExample('Calculator', 'add');

        expect($written)->toContain('should add');
        expect($written)->toContain('$this->calculator->add()');
    });

    it("returns true when an example is added", function (Filesystem $fs) {
        $specPath = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'Calculator.spec.php';

        allow($fs->exists($specPath))->toReturn(true);
        allow($fs->read($specPath))->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            let("calculator", fn() => new Calculator());
            it("instantiates", fn() => expect($this->calculator)->toBeAnInstanceOf(Calculator::class));
        });
        PHP);

        expect($this->generator->addExample('Calculator', 'add'))->toBe(true);
    });

    it("does not add a duplicate example for the same method", function (Filesystem $fs) {
        $specPath = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'Calculator.spec.php';

        allow($fs->exists($specPath))->toReturn(true);
        allow($fs->read($specPath))->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            let("calculator", fn() => new Calculator());
            it("instantiates", fn() => expect($this->calculator)->toBeAnInstanceOf(Calculator::class));

            it("should add", fn() => expect($this->calculator->add())->toBe(null));
        });
        PHP);

        expect($this->generator->addExample('Calculator', 'add'))->toBe(false);
        expect($fs->write($specPath, ''))->not()->toBeCalled();
    });

    it("does not add example when spec file does not exist", function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect($this->generator->addExample('Missing', 'foo'))->toBe(false);

        expect($fs->write('', ''))->not()->toBeCalled();
    });

    it("does nothing when spec file has no closing marker", function (Filesystem $fs) {
        $specPath = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'Broken.spec.php';

        allow($fs->exists($specPath))->toReturn(true);
        allow($fs->read($specPath))->toReturn('<?php // no closing marker');

        expect($this->generator->addExample('Broken', 'foo'))->toBe(false);

        expect($fs->write($specPath, ''))->not()->toBeCalled();
    });

    it("does nothing when spec file already exists", function (Filesystem $fs) {
        $expectedPath = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'Existing.spec.php';

        allow($fs->exists($expectedPath))->toReturn(true);

        $this->generator->generate('Existing');

        expect($fs->write($expectedPath, ''))->not()->toBeCalled();
    });

});
