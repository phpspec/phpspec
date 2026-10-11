<?php

use PhpSpec\Configuration;
use PhpSpec\CommandLine;
use PhpSpec\Filesystem;

describe(CommandLine::class, function () {

    it('reads as the binary, the command and the options that set up the same project', function () {
        $line = new CommandLine('vendor/bin/phpspec', ['--config=conf/qa.yml']);

        expect($line->for('describe', 'App/Basket'))->toBe('vendor/bin/phpspec describe App/Basket --config=conf/qa.yml');
    });

    it('quotes an argument a shell would split or expand, and only that one', function () {
        $line = new CommandLine('phpspec', ["--config=my conf/it's.yml"]);

        expect($line->for('generate', 'a spec for $total'))->toBe("phpspec generate 'a spec for \$total' '--config=my conf/it'\\''s.yml'");
    });

    it('reads as the arguments alone when it has no binary, for a reader who brings their own', function () {
        expect((new CommandLine(null, ['--bootstrap=boot.php']))->for('run', 'spec/App/Basket.spec.php:6'))->toBe('run spec/App/Basket.spec.php:6 --bootstrap=boot.php');
    });

    it('is the phpspec binary with no options when nothing is known of how it was started', function () {
        expect((new CommandLine())->for('guard'))->toBe('phpspec guard');
    });

    it('starts from the binary this process was invoked by, named from the project, with the config it was named', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path): bool => $path === 'conf/qa.yml');
        allow($fs->read(any()))->toReturn("spec_path: spec\n");
        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = [getcwd() . '/vendor/bin/phpspec', 'run'];

        try {
            expect(CommandLine::invoked(Configuration::load('.', $fs, 'conf/qa.yml'))->for('guard'))->toBe('vendor/bin/phpspec guard --config=conf/qa.yml');
        } finally {
            $_SERVER['argv'] = $argv;
        }
    });
});
