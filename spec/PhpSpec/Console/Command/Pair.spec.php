<?php

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Filesystem;
use PhpSpec\Loader;
use PhpSpec\Runner;
use Symfony\Component\Console\Tester\CommandTester;

describe(Pair::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
    });

    it('instantiates', function (Filesystem $fs) {
        $config = new Configuration('.', $fs);
        $cmd = new Pair(
            new Loader($fs),
            new Runner(),
            new SpecGenerator('spec', $fs),
            new ClassGenerator('src', $fs),
            $config,
        );
        expect($cmd)->toBeAnInstanceOf(Pair::class);
    });

    it('rejects non-interactive terminal', function (Filesystem $fs) {
        $config = new Configuration('.', $fs);
        $cmd = new Pair(
            new Loader($fs),
            new Runner(),
            new SpecGenerator('spec', $fs),
            new ClassGenerator('src', $fs),
            $config,
        );

        $tester = new CommandTester($cmd);
        $exitCode = $tester->execute([], ['interactive' => false]);
        expect($exitCode)->toBe(1);
        expect($tester->getDisplay())->toContain('interactive terminal');
    });
});
