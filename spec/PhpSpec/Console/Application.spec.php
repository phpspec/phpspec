<?php

use PhpSpec\Console\Application;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Run;

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
});
