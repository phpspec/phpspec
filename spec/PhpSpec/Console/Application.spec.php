<?php

use PhpSpec\Console\Application;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Run;

describe(Application::class, function() {
    let("application", fn() => new Application());
    it("instantiates", fn() => expect($this->application)->toBeAnInstanceOf(Application::class));

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
