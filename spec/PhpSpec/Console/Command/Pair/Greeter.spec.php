<?php

use PhpSpec\Console\Command\Pair\Greeter;
use PhpSpec\Console\Command\Pair\Notes;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

describe(Greeter::class, function () {

    it('greets from the suite outcome the runner reports', function () {
        $buffer = new BufferedOutput();
        $pairOutput = new PairOutput($buffer);

        $runner = new class implements SpecRunner {
            public function run(string $argument, OutputInterface $output): ?RunOutcome
            {
                return new RunOutcome(null, new SuiteSummary(
                    'red',
                    ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
                    [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
                ));
            }
        };

        (new Greeter($runner, $pairOutput, true, new Notes()))->greet();

        $text = $buffer->fetch();
        expect($text)->toContain('red');
        expect($text)->toContain('App\\Calculator');
    });

    it('runs the whole suite quietly, without leaking the run output', function () {
        $buffer = new BufferedOutput();
        $pairOutput = new PairOutput($buffer);

        $runner = new class implements SpecRunner {
            public string $argument = 'unset';

            public function run(string $argument, OutputInterface $output): ?RunOutcome
            {
                $this->argument = $argument;
                $output->writeln('..... dot noise .....');

                return new RunOutcome(null, new SuiteSummary(
                    'green',
                    ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
                ));
            }
        };

        (new Greeter($runner, $pairOutput, false, new Notes()))->greet();

        $text = $buffer->fetch();
        expect($runner->argument)->toBe('');
        expect($text)->not()->toContain('dot noise');
        expect($text)->toContain('Green');
        expect($text)->toContain('describe');
    });


    it('leaves what it said for the model to read with the next answer', function () {
        $notes = new Notes();
        $runner = new class implements SpecRunner {
            public function run(string $argument, OutputInterface $output): ?RunOutcome
            {
                return new RunOutcome(null, new SuiteSummary(
                    'red',
                    ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
                    [['subject' => 'App\\Calculator', 'example' => 'adds numbers']],
                ));
            }
        };

        (new Greeter($runner, new PairOutput(new BufferedOutput()), true, $notes))->greet();

        $brief = $notes->brief('yes');

        expect($brief)->toContain("App\\Calculator");
        expect($brief)->toContain('Want to start there?');
        expect($brief)->not()->toContain('/quit');
        expect($notes->brief('yes'))->toBe('yes');
    });
});
