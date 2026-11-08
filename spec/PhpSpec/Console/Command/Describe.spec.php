<?php

use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Console\Prompt;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Filesystem;
use PhpSpec\Source\ComposerJson;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

describe(Describe::class, function() {

    this("describe", fn(Filesystem $fs) => new Describe(new SpecGenerator('spec', $fs)));

    it("instantiates", fn() => expect($this->describe)->toBeAnInstanceOf(Describe::class));

    it("delegates generation to spec generator", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        allow($fs->mkdir(any()));
        expect($fs->write(cetera()))->toBeCalled();
        $this->describe->run(new ArrayInput(['class' => 'Some/Spec']), new NullOutput());
    });

    it("calls addExample when -e option is provided", function(Filesystem $fs) {
        // generate() call
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match (true) {
            str_ends_with($path, '.spec.php') => true,
            default => true,
        });
        // addExample() reads the spec file
        allow($fs->read(any()))->toReturn(<<<'PHP'
        <?php

        describe(Spec::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);

        expect($fs->write(cetera()))->toBeCalled();
        $this->describe->run(
            new ArrayInput(['class' => 'Some/Spec', '--exemplify' => 'doSomething']),
            new NullOutput()
        );
    });

    it("names the spec it wrote from the project root, in colour, after a blank line", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        allow($fs->mkdir(any()));
        allow($fs->write(cetera()));

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
            allow($fs->exists(any()))->toReturn(false);
            allow($fs->mkdir(any()));
            allow($prompt->ask(any()))->toReturn('2');
            expect($fs->write(satisfy($specFile('spec/Another/Acme/Thing.spec.php')), any()))->toBeCalled();

            $output = new BufferedOutput();
            $exit = (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), $output);

            expect($exit)->toBe(0);
            expect($output->fetch())->toContain('[1] Brew\Acme\Thing')->toContain('[2] Another\Acme\Thing')->toContain('[0] Acme\Thing, as written')->toContain('Another\Acme\Thing created in spec/Another/Acme/Thing.spec.php');
        });

        it("keeps the name as written when told to", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists(any()))->toReturn(false);
            allow($fs->mkdir(any()));
            allow($prompt->ask(any()))->toReturn('0');
            expect($fs->write(satisfy($specFile('spec/Acme/Thing.spec.php')), any()))->toBeCalled();

            (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), new NullOutput());
        });

        it("maps the name kept as written for Composer, and says so", function (Filesystem $fs, Prompt $prompt) use ($mapped) {
            $files = new ArrayObject(['/app/composer.json' => '{"autoload": {"psr-4": {"Brew\\\\": "src/Brew/", "Another\\\\": "src/Another/"}}}']);
            allow($fs->exists(any()))->toReturnUsing(fn(string $path): bool => isset($files[$path]));
            allow($fs->read(any()))->toReturnUsing(fn(string $path): string => $files[$path] ?? '');
            allow($fs->write(cetera()))->toReturnUsing(function (string $path, string $content) use ($files): void {
                $files[$path] = $content;
            });
            allow($fs->mkdir(any()));
            allow($prompt->ask(any()))->toReturn('0');

            $output = new BufferedOutput();
            (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt, new ComposerJson('/app/composer.json', $fs)))->run(new ArrayInput(['class' => 'Acme/Thing']), $output);

            expect($files['/app/composer.json'])->toContain('"": "src/"');
            expect($output->fetch())->toContain('Composer autoload was updated to include "": "src/". Run composer dumpautoload next.');
        });

        it("does not offer the name as written where Composer maps the source path to another namespace", function (Filesystem $fs, Prompt $prompt) use ($specFile) {
            $files = new ArrayObject(['/app/composer.json' => '{"autoload": {"psr-4": {"Other\\\\": "src/"}}}']);
            allow($fs->exists(any()))->toReturnUsing(fn(string $path): bool => isset($files[$path]));
            allow($fs->read(any()))->toReturnUsing(fn(string $path): string => $files[$path] ?? '');
            allow($fs->mkdir(any()));
            $answers = ['0', '1'];
            allow($prompt->ask(any()))->toReturnUsing(function () use (&$answers) {
                return array_shift($answers);
            });
            expect($fs->write(satisfy($specFile('spec/Other/Brew/Acme/Sombrero.spec.php')), any()))->toBeCalled();

            $output = new BufferedOutput();
            (new Describe(new SpecGenerator('spec', $fs), new SourceLayout('src', ['Other\\' => 'src']), $prompt, new ComposerJson('/app/composer.json', $fs)))->run(new ArrayInput(['class' => 'Brew/Acme/Sombrero']), $output);

            $said = $output->fetch();
            expect($said)->not()->toContain('as written');
            expect($said)->toContain('from 1 to 1');
            expect($files['/app/composer.json'])->toBe('{"autoload": {"psr-4": {"Other\\\\": "src/"}}}');
        });

        it("puts it into the default namespace without asking", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists(any()))->toReturn(false);
            allow($fs->mkdir(any()));
            expect($fs->write(satisfy($specFile('spec/Brew/Acme/Thing.spec.php')), any()))->toBeCalled();
            expect($prompt->ask(any()))->not()->toBeCalled();

            (new Describe(new SpecGenerator('spec', $fs), $mapped('Brew'), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), new NullOutput());
        });

        it("writes nothing and says why when nobody can answer, with the remedies", function (Filesystem $fs, Prompt $prompt) use ($mapped) {
            allow($fs->exists(any()))->toReturn(false);
            expect($fs->write(cetera()))->not()->toBeCalled();
            expect($prompt->ask(any()))->not()->toBeCalled();

            $input = new ArrayInput(['class' => 'Acme/Thing']);
            $input->setInteractive(false);
            $output = new BufferedOutput();
            $exit = (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run($input, $output);

            expect($exit)->toBe(1);
            expect($output->fetch())->toContain('Acme\Thing is under none of the mapped namespaces: Brew\, Another\.')->toContain('Brew\Acme\Thing or Another\Acme\Thing')->toContain('default_namespace');
        });

        it("answers an agent with the error and the remedy", function (Filesystem $fs, Prompt $prompt) use ($mapped) {
            allow($fs->exists(any()))->toReturn(false);
            expect($fs->write(cetera()))->not()->toBeCalled();

            $output = new BufferedOutput();
            $exit = (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing', '--format' => 'agent']), $output);

            $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
            expect($exit)->toBe(1);
            expect($doc['action'])->toBe('describe');
            expect($doc['error'])->toContain('under none of the mapped namespaces');
            expect($doc['remedy'])->toContain('default_namespace');
        });

        it("treats an empty answer as the first choice and asks again on a number it does not have", function (Filesystem $fs, Prompt $prompt) use ($mapped, $specFile) {
            allow($fs->exists(any()))->toReturn(false);
            allow($fs->mkdir(any()));
            $answers = ['7', ''];
            allow($prompt->ask(any()))->toReturnUsing(function () use (&$answers) {
                return array_shift($answers);
            });
            expect($fs->write(satisfy($specFile('spec/Brew/Acme/Thing.spec.php')), any()))->toBeCalled();

            $output = new BufferedOutput();
            (new Describe(new SpecGenerator('spec', $fs), $mapped(), $prompt))->run(new ArrayInput(['class' => 'Acme/Thing']), $output);

            expect($output->fetch())->toContain('from 0 to 2');
        });
    });

    it("does not add example without -e option", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'Some/Spec']), $output);
        expect($output->fetch())->not()->toContain("Example for method");
    });

    it("emits a JSON receipt with --format=agent instead of prose", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        allow($fs->mkdir(any()));
        allow($fs->write(cetera()));

        $output = new BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'App/Basket', '--format' => 'agent']), $output);

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc)->toBe([
            'v' => 2,
            'action' => 'describe',
            'class' => 'App\\Basket',
            'spec' => 'spec/App/Basket.spec.php',
            'created' => true,
        ]);
    });

    it("says the spec already existed, and writes nothing, when it did", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        expect($fs->write(cetera()))->not()->toBeCalled();

        $output = new BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'App/Basket']), $output);

        $text = $output->fetch();
        expect($text)->toContain('Specification for App/Basket already exists in spec/App/Basket.spec.php');
        expect($text)->not()->toContain('created');
    });

    it("says the example already existed when -e names a method the spec has", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        allow($fs->read(any()))->toReturn("<?php\n\ndescribe(Spec::class, function() {\n    it(\"should greet\", fn() => null);\n});\n");
        expect($fs->write(cetera()))->not()->toBeCalled();

        $output = new BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'Some/Spec', '--exemplify' => 'greet']), $output);

        $text = $output->fetch();
        expect($text)->toContain('Example for method greet already exists.');
        expect($text)->not()->toContain('added');
    });

    it("reports created false in the receipt when the spec already exists", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);

        $output = new BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'App/Basket', '--format' => 'agent']), $output);

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc['created'])->toBe(false);
    });

    it("includes the example receipt when --format=agent is combined with -e", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        allow($fs->read(any()))->toReturn(<<<'PHP'
        <?php

        describe(Spec::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);
        allow($fs->write(cetera()));

        $output = new BufferedOutput();
        $this->describe->run(
            new ArrayInput(['class' => 'App/Basket', '--format' => 'agent', '--exemplify' => 'checkout']),
            $output
        );

        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
        expect($doc['example'])->toBe(['method' => 'checkout', 'added' => true]);
    });

    it("outputs confirmation when -e option is used", function(Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(true);
        allow($fs->read(any()))->toReturn(<<<'PHP'
        <?php

        describe(Spec::class, function() {
            it("instantiates", fn() => null);
        });
        PHP);
        allow($fs->write(cetera()));

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $this->describe->run(
            new ArrayInput(['class' => 'Some/Spec', '--exemplify' => 'greet']),
            $output
        );
        expect($output->fetch())->toContain("Example for method greet added.");
    });

});
