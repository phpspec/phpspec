<?php

use PhpSpec\Extensions\CommandExtension;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

describe(CommandExtension::class, function () {

    let('command', fn() => new class extends CommandExtension {
        public function getName(): string
        {
            return 'greet';
        }

        public function getDescription(): string
        {
            return 'Say hello';
        }

        public function execute(InputInterface $input, OutputInterface $output): int
        {
            $output->writeln('Hello!');
            return 0;
        }
    });

    it('returns the command name', function () {
        expect($this->command->getName())->toBe('greet');
    });

    it('returns the description', function () {
        expect($this->command->getDescription())->toBe('Say hello');
    });

    it('executes and returns exit code', function (InputInterface $input, OutputInterface $output) {
        expect($this->command->execute($input, $output))->toBe(0);
        expect($output->writeln('Hello!'))->toBeCalled();
    });
});
