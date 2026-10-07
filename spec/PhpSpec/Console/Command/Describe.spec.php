<?php

use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Console\Prompt;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

describe(Describe::class, function() {

    let("describe", fn(Filesystem $fs) => new Describe(new SpecGenerator('spec', $fs)));

    it("instantiates", fn() => expect($this->describe)->toBeAnInstanceOf(Describe::class));

    it("delegates generation to spec generator", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        expect($fs->write())->toBeCalled();
        $this->describe->run(new ArrayInput(['class' => 'Some/Spec']), new NullOutput());
    });

    it("calls addExample when -e option is provided", function(Filesystem $fs) {
        // generate() call
        allow($fs->exists())->toReturnUsing(fn(string $path) => match (true) {
            str_ends_with($path, '.spec.php') => true,
            default => true,
        });
        // addExample() reads the spec file
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Spec::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);

        expect($fs->write())->toBeCalled();
        $this->describe->run(
            new ArrayInput(['class' => 'Some/Spec', '--exemplify' => 'doSomething']),
            new NullOutput()
        );
    });

    it("names the spec it wrote from the project root, in colour, after a blank line", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->describe->run(new ArrayInput(['class' => 'App/Calculator']), $output);

        expect($output->fetch())->toBe(
            PHP_EOL . "\e[32mSpecification for \e[39m\e[33mApp/Calculator\e[39m\e[32m created in \e[39m\e[33mspec/App/Calculator.spec.php\e[39m" . PHP_EOL,
        );
    });

    context("a class under none of the mapped namespaces", function () {
        $mapped = fn(?string $default = null): SourceLayout => new SourceLayout('src', ['Brew\\' => 'src/Brew', 'Another\\' => 'src/Another'], $default);
        $specFile = fn(string $path): callable => fn(string $written): bool => str_ends_with($written, str_replace('/', DIRECTORY_SEPARATOR, $path));

        it("asks which mapped namespace to describe it under, and describes the answer", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists())->toReturn(false);
            allow($fs->mkdir());
            allow($prompt->ask())->toReturn('2');
            expect($fs->write(satisfy($specFile('spec/Another/Acme/Thing.spec.php')), any()))->toBeCalled();

            $output = new BufferedOutput();
            $exit = (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), $output);

            expect($exit)->toBe(0);
            expect($output->fetch())->toContain('[1] Brew\Acme\Thing')->toContain('[2] Another\Acme\Thing')->toContain('[0] Acme\Thing, as written')->toContain('Another\Acme\Thing created in spec/Another/Acme/Thing.spec.php');
        });

        it("keeps the name as written when told to", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists())->toReturn(false);
            allow($fs->mkdir());
            allow($prompt->ask())->toReturn('0');
            expect($fs->write(satisfy($specFile('spec/Acme/Thing.spec.php')), any()))->toBeCalled();

            (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), new NullOutput());
        });

        it("puts it into the default namespace without asking", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists())->toReturn(false);
            allow($fs->mkdir());
            expect($fs->write(satisfy($specFile('spec/Brew/Acme/Thing.spec.php')), any()))->toBeCalled();
            expect($prompt->ask())->not()->toBeCalled();

            (new Describe(new SpecGenerator('spec', $fs), $mapped('Brew'), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), new NullOutput());
        });

        it("writes nothing and says why when nobody can answer, with the remedies", function (Filesystem $fs, Prompt $prompt) use ($mapped) {
            allow($fs->exists())->toReturn(false);
            expect($fs->write())->not()->toBeCalled();
            expect($prompt->ask())->not()->toBeCalled();

            $input = new ArrayInput(['class' => 'Acme/Thing']);
            $input->setInteractive(false);
            $output = new BufferedOutput();
            $exit = (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run($input, $output);

            expect($exit)->toBe(1);
            expect($output->fetch())->toContain('Acme\Thing is under none of the mapped namespaces: Brew\, Another\.')->toContain('Brew\Acme\Thing or Another\Acme\Thing')->toContain('default_namespace');
        });

        it("answers an agent with the error and the remedy", function (Filesystem $fs, Prompt $prompt) use ($mapped) {
            allow($fs->exists())->toReturn(false);
            expect($fs->write())->not()->toBeCalled();

            $output = new BufferedOutput();
            $exit = (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing', '--format' => 'agent']), $output);

            $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
            expect($exit)->toBe(1);
            expect($doc['action'])->toBe('describe');
            expect($doc['error'])->toContain('under none of the mapped namespaces');
            expect($doc['remedy'])->toContain('default_namespace');
        });

        it("treats an empty answer as the first choice and asks again on a number it does not have", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists())->toReturn(false);
            allow($fs->mkdir());
            $answers = ['7', ''];
            allow($prompt->ask())->toReturnUsing(function () use (&$answers) {
                return array_shift($answers);
            });
            expect($fs->write(satisfy($specFile('spec/Brew/Acme/Thing.spec.php')), any()))->toBeCalled();

            $output = new BufferedOutput();
            (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), $output);

            expect($output->fetch())->toContain('from 0 to 2');
        });
    });

    it("does not add example without -e option", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'Some/Spec']), $output);
        expect($output->fetch())->not()->toContain("Example for method");
    });

    it("emits a JSON receipt with --agent instead of prose", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $output = new BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'App/Basket', '--agent' => true]), $output);

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc)->toBe([
            'v' => 2,
            'action' => 'describe',
            'class' => 'App\\Basket',
            'spec' => 'spec/App/Basket.spec.php',
            'created' => true,
        ]);
    });

    it("reports created false in the receipt when the spec already exists", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(true);

        $output = new BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'App/Basket', '--agent' => true]), $output);

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc['created'])->toBe(false);
    });

    it("includes the example receipt when --agent is combined with -e", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Spec::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);
        allow($fs->write())->toReturn(null);

        $output = new BufferedOutput();
        $this->describe->run(
            new ArrayInput(['class' => 'App/Basket', '--agent' => true, '--exemplify' => 'checkout']),
            $output
        );

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc['example'])->toBe(['method' => 'checkout', 'added' => true]);
    });

    it("outputs confirmation when -e option is used", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn(<<<'PHP'
        <?php

        describe(Spec::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);
        allow($fs->write())->toReturn(null);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $this->describe->run(
            new ArrayInput(['class' => 'Some/Spec', '--exemplify' => 'greet']),
            $output
        );
        expect($output->fetch())->toContain("Example for method greet added.");
    });

});
