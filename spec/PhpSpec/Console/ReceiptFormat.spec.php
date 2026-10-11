<?php

use PhpSpec\Console\ReceiptFormat;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

describe(ReceiptFormat::class, function () {

    let('input', fn() => fn(string $format): ArrayInput => new ArrayInput(['--format' => $format], new InputDefinition([new InputOption('format', 'f', InputOption::VALUE_REQUIRED, '', 'pretty')])));

    it('reads the format a command was asked for, pretty unless agent', function () {
        expect(ReceiptFormat::of(($this->input)('pretty')))->toBe(ReceiptFormat::Pretty);
        expect(ReceiptFormat::of(($this->input)('agent')))->toBe(ReceiptFormat::Agent);
    });

    it('refuses a format it does not speak, naming the ones it does', function () {
        expect(fn() => ReceiptFormat::of(($this->input)('json')))->toThrow(new InvalidArgumentException('Unknown format: json (available: pretty, agent)'));
    });
});
