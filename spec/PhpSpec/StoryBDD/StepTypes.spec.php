<?php

use PhpSpec\StoryBDD\DataTable;
use PhpSpec\StoryBDD\DocStringType;
use PhpSpec\StoryBDD\ParameterType;
use PhpSpec\StoryBDD\StepTypes;

final class StepTypesSpecBook
{
    public function __construct(public string $author, public string $isbn) {}
}

if (!function_exists('step_types_spec_book')) {
    #[ParameterType]
    function step_types_spec_book(string $author, string $isbn): StepTypesSpecBook
    {
        return new StepTypesSpecBook($author, $isbn);
    }

    #[DocStringType('json')]
    function step_types_spec_json(string $text): array
    {
        return json_decode($text, true);
    }
}

describe(StepTypes::class, function () {

    let('refusal', fn() => function (callable $make): ?string {
        try {
            $make();
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    });

    let('types', fn() => StepTypes::of(new ReflectionFunction('step_types_spec_book'), new ReflectionFunction('step_types_spec_json')));

    context('found', function () {
        it('finds the functions the code declared with a type attribute', function () {
            $arguments = StepTypes::declared()->arguments(fn(StepTypesSpecBook $book) => null, ['Ann', '123'], null, null);

            expect($arguments[0])->toBeLike(new StepTypesSpecBook('Ann', '123'));
        });

        it('refuses two functions that make one class', function () {
            $other = #[ParameterType] fn(string $title): StepTypesSpecBook => new StepTypesSpecBook($title, '');

            expect(($this->refusal)(fn() => StepTypes::of(new ReflectionFunction('step_types_spec_book'), new ReflectionFunction($other))))
                ->toStartWith('step_types_spec_book() and {closure')->toEndWith('() both make StepTypesSpecBook.');
        });

        it('refuses a parameter type that returns no class', function () {
            $count = #[ParameterType] fn(string $n): int => (int) $n;

            expect(($this->refusal)(fn() => StepTypes::of(new ReflectionFunction($count))))
                ->toStartWith('#[ParameterType] {closure')->toEndWith('() returns no class.');
        });

        it('refuses two functions for one doc string type', function () {
            $other = #[DocStringType('json')] fn(string $text): string => $text;

            expect(($this->refusal)(fn() => StepTypes::of(new ReflectionFunction('step_types_spec_json'), new ReflectionFunction($other))))
                ->toStartWith('step_types_spec_json() and {closure')->toEndWith('() both make the "json" doc string.');
        });

        it('refuses a doc string type that does not take one parameter', function () {
            $two = #[DocStringType('xml')] fn(string $a, string $b): string => $a . $b;

            expect(($this->refusal)(fn() => StepTypes::of(new ReflectionFunction($two))))
                ->toStartWith('#[DocStringType] {closure')->toEndWith('() must take one parameter.');
        });
    });

    context('arguments', function () {
        it('makes a typed parameter from as many captures as its function takes, and passes the others as they are', function () {
            $arguments = $this->types->arguments(fn(StepTypesSpecBook $book, int $price) => null, ['Ann', '123', '9'], null, null);

            expect($arguments)->toBeLike([new StepTypesSpecBook('Ann', '123'), '9']);
        });

        it('passes captures no parameter takes, and those a variadic takes, as they are', function () {
            expect($this->types->arguments(fn(string ...$all) => null, ['a', 'b'], null, null))->toBe(['a', 'b']);
            expect($this->types->arguments(fn(string $first) => null, ['a', 'b'], null, null))->toBe(['a', 'b']);
        });

        it('passes the doc string through the type its parameter is named after, then the table', function () {
            $table = new DataTable([['name'], ['Ann']]);

            $arguments = $this->types->arguments(fn(string $who, array $json, DataTable $rows) => null, ['Ann'], '{"debug": true}', $table);

            expect($arguments)->toBe(['Ann', ['debug' => true], $table]);
        });

        it('passes a doc string as it is when its parameter is named after no type', function () {
            expect($this->types->arguments(fn(string $text) => null, [], 'plain', null))->toBe(['plain']);
        });

        it('passes a doc string as it is to a step with no parameter left for it', function () {
            expect($this->types->arguments(fn() => null, [], 'plain', null))->toBe(['plain']);
        });

        it('refuses a type whose function takes more captures than the step has left', function () {
            expect(fn() => $this->types->arguments(fn(int $price, StepTypesSpecBook $book) => null, ['9', 'Ann'], null, null))
                ->toThrow(RuntimeException::class, 'step_types_spec_book() takes 2 captures, the step has 1 left.');
        });
    });
});
