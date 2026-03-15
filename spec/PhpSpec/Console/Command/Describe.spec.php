<?php

use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

describe(Describe::class, function() {

    let("describe", fn(Filesystem $fs) => new Describe(new SpecGenerator('spec', $fs)));

    it("instantiates", fn() => expect($this->describe)->toBeAnInstanceOf(Describe::class));

    it("delegates generation to spec generator", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        expect($fs->write('', ''))->toBeCalled();
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

        expect($fs->write('', ''))->toBeCalled();
        $this->describe->run(
            new ArrayInput(['class' => 'Some/Spec', '--exemplify' => 'doSomething']),
            new NullOutput()
        );
    });

    it("does not add example without -e option", function(Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $this->describe->run(new ArrayInput(['class' => 'Some/Spec']), $output);
        expect($output->fetch())->not()->toContain("Example for method");
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
