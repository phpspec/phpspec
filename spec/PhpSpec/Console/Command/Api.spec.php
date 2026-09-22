<?php

use PhpSpec\Console\Command\Api;
use Symfony\Component\Console\Tester\CommandTester;

describe(Api::class, function () {

    let('document', function () {
        $tester = new CommandTester(new Api());
        $tester->execute(['--format' => 'agent']);

        return json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
    });

    it('answers a coding agent with one JSON object describing the spec-writing surface', function () {
        expect($this->document['v'])->toBe(2);
        expect($this->document['action'])->toBe('api');

        foreach (['timing', 'dsl', 'matchers', 'negation', 'mocks', 'story', 'browser', 'docs'] as $section) {
            expect($this->document)->toHaveKey($section);
        }
    });

    it('reflects each function with its real signature and a summary', function () {
        $byName = array_column($this->document['dsl'], null, 'name');

        expect($byName['it']['signature'])->toBe('it(string $title, Closure $example): void');
        expect($byName['let']['signature'])->toBe('let(Closure|string $propertyOrSetter, ?Closure $setter = null): void');
        expect($byName['attach']['signature'])->toBe('PhpSpec\attach(string $name, Closure|string $value): void');
        expect($byName['it']['summary'])->toBe('Registers a new example (test case) in the current context scope.');
    });

    it('leaves nothing in the document without a summary', function () {
        $entries = array_merge(
            $this->document['dsl'],
            $this->document['matchers'],
            [$this->document['negation']],
            $this->document['mocks']['functions'],
            $this->document['mocks']['expectations'],
            $this->document['mocks']['argument_matchers'],
            $this->document['story']['steps'],
            $this->document['story']['hooks'],
            $this->document['browser']['functions'],
        );

        foreach ($entries as $entry) {
            expect($entry['summary'])->not()->toBeEmpty();
        }
    });

    it('marks the matcher that runs its subject where it is written', function () {
        $byName = array_column($this->document['matchers'], null, 'name');

        expect($byName['toThrow']['runs_subject'])->toBeTrue();
        expect($byName['toBe'])->not()->toHaveKey('runs_subject');
    });

    it('states the timing rules a reader cannot introspect', function () {
        $timing = implode(' ', $this->document['timing']);

        expect($timing)->toContain('end of the example');
        expect($timing)->toContain('toThrow()');
    });

    it('carries the version of the application it runs in', function () {
        $tester = new CommandTester((new \PhpSpec\Console\Application('9.9.9'))->find('api'));
        $tester->execute(['--format' => 'agent']);
        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);

        expect($document['version'])->toBe('9.9.9');
    });

    it('prints the same surface as prose by default', function () {
        $tester = new CommandTester(new Api());
        $tester->execute([]);
        $display = $tester->getDisplay();

        expect($tester->getStatusCode())->toBe(0);
        expect($display)->toContain('Timing');
        expect($display)->toContain('describe(string $context, Closure $examples): void');
        expect($display)->toContain('Registers a new example (test case) in the current context scope.');
        expect($display)->toContain('toThrow(string $exceptionClass = \'\', ?string $message = null): static');
        expect($display)->toContain('mock(string $class): object');
        expect($display)->toContain('given(string $pattern, Closure $fn): void');
    });

});
