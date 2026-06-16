<?php

use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Console\Command\Exemplify;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Exemplify::class, function () {

    let('exemplify', fn(Filesystem $fs) => new Exemplify(new SpecGenerator('spec', $fs)));

    it('instantiates', fn() => expect($this->exemplify)->toBeAnInstanceOf(Exemplify::class));

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

        $output = new BufferedOutput();
        $this->exemplify->run(
            new ArrayInput(['class' => 'Acme\Calculator', 'method' => 'add']),
            $output
        );
        expect($output->fetch())->toContain('Example for Acme\Calculator::add added.');
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
