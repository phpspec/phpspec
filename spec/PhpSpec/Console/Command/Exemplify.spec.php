<?php

use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Console\Command\Exemplify;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Exemplify::class, function () {

    let('exemplify', fn(Filesystem $fs) => new Exemplify(new SpecGenerator('spec', $fs)));

    it('instantiates', fn() => expect($this->exemplify)->toBeAnInstanceOf(Exemplify::class));

    it('puts a class under no mapping into the default namespace', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir());
        allow($fs->read())->toReturn("<?php\n\ndescribe(Thing::class, function() {\n    it(\"instantiates\", fn() => null);\n});\n");
        $layout = new SourceLayout('src', ['Brew\\Acme\\' => 'src/Brew/Acme'], 'Brew\\Acme');
        expect($fs->write(satisfy(fn(string $path): bool => str_ends_with($path, str_replace('/', DIRECTORY_SEPARATOR, 'spec/Brew/Acme/Thing.spec.php'))), any()))->toBeCalled();

        $output = new BufferedOutput();
        (new Exemplify(new SpecGenerator('spec', $fs), $layout))->run(new ArrayInput(['class' => 'Thing', 'method' => 'spin']), $output);

        expect($output->fetch())->toContain('Brew\Acme\Thing::spin');
    });

    it('generates spec and adds example', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(function (string $p) {
            static $specCalls = 0;
            if (str_ends_with($p, '.spec.php')) {
                return $specCalls++ > 0;
            }
            return false;
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->exemplify->run(
            new ArrayInput(['class' => 'Acme\Calculator', 'method' => 'add']),
            $output
        );
        expect($output->fetch())->toBe(PHP_EOL . "\e[32mExample for \e[39m\e[33mAcme\\Calculator::add\e[39m\e[32m added.\e[39m" . PHP_EOL);
    });

    it('emits a JSON receipt with --agent instead of prose', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);
        allow($fs->write())->toReturn(null);

        $output = new BufferedOutput();
        $this->exemplify->run(
            new ArrayInput(['class' => 'Acme\Calculator', 'method' => 'add', '--agent' => true]),
            $output
        );

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc)->toBe([
            'v' => 2,
            'action' => 'exemplify',
            'class' => 'Acme\\Calculator',
            'method' => 'add',
            'spec' => 'spec/Acme/Calculator.spec.php',
            'added' => true,
        ]);
    });

    it('says the example already existed instead of claiming to have added it', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n\ndescribe(Calculator::class, function() {\n    it(\"should add\", fn() => null);\n});\n");
        expect($fs->write())->not()->toBeCalled();

        $output = new BufferedOutput();
        $this->exemplify->run(new ArrayInput(['class' => 'Acme\Calculator', 'method' => 'add']), $output);

        $text = $output->fetch();
        expect($text)->toContain('Example for Acme\Calculator::add already exists.');
        expect($text)->not()->toContain('added');
    });

    it('reports added false in the receipt when the example already exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            it("instantiates", fn() => null);
            it("should add", fn() => null);
        });
        PHP);
        allow($fs->write())->toReturn(null);

        $output = new BufferedOutput();
        $this->exemplify->run(
            new ArrayInput(['class' => 'Acme\Calculator', 'method' => 'add', '--agent' => true]),
            $output
        );

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc['added'])->toBe(false);
    });

    it('does not duplicate spec when it already exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Calculator::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);
        allow($fs->write())->toReturn(null);

        $output = new BufferedOutput();
        $this->exemplify->run(
            new ArrayInput(['class' => 'Acme\Calculator', 'method' => 'subtract']),
            $output
        );
        expect($output->fetch())->toContain('Example for Acme\Calculator::subtract added.');
    });
});
