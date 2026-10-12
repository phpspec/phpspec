<?php

use PhpSpec\Console\Application;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Run;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Application::class, function() {
    let("application", fn() => new Application());
    it("instantiates", fn() => expect($this->application)->toBeAnInstanceOf(Application::class));

    it("is named phpspec and carries the version it was given as its version", function () {
        $application = new Application('9.9.9');

        expect($application->getName())->toBe('phpspec');
        expect($application->getVersion())->toBe('9.9.9');
    });

    it("has a describe command", function () {
        expect(array_filter(
            $this->application->getDefaultCommands(),
            fn($command) => $command instanceof Describe
        ))->toHaveCount(1);
    });

    it("has a run command", function () {
        expect(array_filter(
            $this->application->getDefaultCommands(),
            fn($command) => $command instanceof Run
        ))->toHaveCount(1);
    });

    it("has a pair command", function () {
        expect(array_filter(
            $this->application->getDefaultCommands(),
            fn($command) => $command instanceof Pair
        ))->toHaveCount(1);
    });

    it("answers a coding agent a command line it cannot read with a run's fatal and summary", function () {
        $output = new BufferedOutput();

        $exit = $this->application->doRun(new ArgvInput(['phpspec', 'run', '--format=agent', '--no-such-option']), $output);
        $events = array_map(fn(string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), explode("\n", trim($output->fetch())));

        expect($exit)->toBe(1);
        expect(array_column($events, 'event'))->toBe(['run_started', 'fatal', 'summary']);
        expect($events[1]['message'])->toBe('The "--no-such-option" option does not exist.');
        expect($events[2]['actionable'])->toBe(1);
    });

    it("answers a receipt command's agent a command line it cannot read with an error receipt", function () {
        $output = new BufferedOutput();

        $exit = $this->application->doRun(new ArgvInput(['phpspec', 'describe', 'App/Basket', '--agent', '--no-such-option']), $output);

        expect($exit)->toBe(1);
        expect(json_decode(trim($output->fetch()), true))->toBe(['v' => 2, 'action' => 'describe', 'error' => 'The "--no-such-option" option does not exist.']);
    });

    it("leaves a command line a person mistyped to the console to explain, with the usage", function () {
        expect(fn() => $this->application->doRun(new ArgvInput(['phpspec', 'run', '--no-such-option']), new BufferedOutput()))
            ->toThrow(RuntimeException::class);
    });
});
