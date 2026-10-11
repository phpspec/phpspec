<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run;
use PhpSpec\Console\Command\Run\OptionValues;
use PhpSpec\Loader;
use PhpSpec\Runner;
use Symfony\Component\Console\Input\ArrayInput;

describe(OptionValues::class, function () {

    let('values', fn() => fn(array $options): OptionValues => new OptionValues(new ArrayInput($options, (new Run(new Loader(), new Runner(), new Configuration()))->getDefinition())));

    it('takes a run given no values at all', function () {
        expect(($this->values)([])->problem())->toBeNull();
    });

    it('takes every value a run can use', function () {
        expect(($this->values)([
            '--order' => 'random',
            '--seed' => '-7',
            '--profile' => '3',
            '--coverage-min' => '95.5',
            '--parallel' => '2',
            '--filter' => 'adds',
            '--coverage-html' => 'build/coverage',
            '--out' => ['report.html'],
        ])->problem())->toBeNull();
    });

    it('names the option and what it takes when a value cannot be taken', function () {
        expect(($this->values)(['--order' => 'bogus'])->problem())->toBe('--order takes default or random, not "bogus".');
        expect(($this->values)(['--seed' => '1.5'])->problem())->toBe('--seed takes a whole number, not "1.5".');
        expect(($this->values)(['--profile' => '0'])->problem())->toBe('--profile takes a number of examples, 1 or more, not "0".');
        expect(($this->values)(['--coverage-min' => 'abc'])->problem())->toBe('--coverage-min takes a percentage from 0 to 100, not "abc".');
        expect(($this->values)(['--coverage-min' => '100.1'])->problem())->toBe('--coverage-min takes a percentage from 0 to 100, not "100.1".');
        expect(($this->values)(['--parallel' => '0'])->problem())->toBe('--parallel takes a number of workers, 1 or more, not "0".');
        expect(($this->values)(['--filter' => ' '])->problem())->toBe('--filter takes the text to select by, not " ".');
        expect(($this->values)(['--coverage-src' => ''])->problem())->toBe('--coverage-src takes the source directory to cover, not "".');
        expect(($this->values)(['--out' => ['report.xml', '']])->problem())->toBe('--out takes a file to write the report to, not "".');
    });

    it('refuses a seed for a run named to keep its order', function () {
        expect(($this->values)(['--order' => 'default', '--seed' => '42'])->problem())->toBe('--seed shuffles a random run, and --order default does not shuffle.');
    });

    it('shuffles a run asked for random order, or given a seed and no order', function () {
        expect(($this->values)(['--order' => 'random'])->shuffles())->toBeTrue();
        expect(($this->values)(['--seed' => '42'])->shuffles())->toBeTrue();
        expect(($this->values)([])->shuffles())->toBeFalse();
        expect(($this->values)(['--order' => 'default'])->shuffles())->toBeFalse();
    });

    it('leaves a --parallel value that is not a number to run as the path it swallowed', function () {
        expect(($this->values)(['--parallel' => 'features'])->problem())->toBeNull();
    });
});
