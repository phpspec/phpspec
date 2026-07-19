<?php

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\CommandDispatcher;
use PhpSpec\Console\Command\Pair\LineEditor;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\Repl;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ReplFakeRunner implements SpecRunner
{
    public function run(string $argument, OutputInterface $output): ?RunOutcome
    {
        return null;
    }
}

describe(Repl::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    let('buffer', fn() => new BufferedOutput());
    let('pairOutput', fn() => new PairOutput($this->buffer));
    let('dispatcher', fn(Filesystem $fs) => new CommandDispatcher(
        new SpecGenerator('spec', $fs),
        new ClassGenerator('src', $fs),
        new Configuration('.', $fs),
        $this->pairOutput,
        false,
        $fs,
        specRunner: new ReplFakeRunner(),
    ));

    // A LineEditor whose line reader replays a script, then EOF (null → break).
    $scripted = function (PairOutput $out, array $lines): LineEditor {
        return new LineEditor($out, function () use (&$lines) {
            return array_shift($lines);
        });
    };

    it('reads a line, dispatches it, and exits cleanly on EOF', function () use ($scripted) {
        $repl = new Repl($this->dispatcher, $this->pairOutput, false, $scripted($this->pairOutput, ['/help']));

        expect($repl->run())->toBe(0);
        expect($this->buffer->fetch())->toContain('Available commands');
    });

    it('quits on /quit', function () use ($scripted) {
        $repl = new Repl($this->dispatcher, $this->pairOutput, false, $scripted($this->pairOutput, ['/quit']));

        expect($repl->run())->toBe(0);
        expect($this->buffer->fetch())->toContain('Goodbye');
    });

});
