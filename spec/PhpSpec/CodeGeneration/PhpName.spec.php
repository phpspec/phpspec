<?php

use PhpSpec\CodeGeneration\PhpName;

describe(PhpName::class, function () {

    context('class names', function () {
        it('accepts a plain class', function () {
            expect(PhpName::classProblem('Basket'))->toBeNull();
        });

        it('accepts a namespaced class, written either way round', function () {
            expect(PhpName::classProblem('App\\Domain\\Basket'))->toBeNull();
            expect(PhpName::classProblem('App/Domain/Basket'))->toBeNull();
        });

        it('accepts underscores and digits after the first character', function () {
            expect(PhpName::classProblem('_Legacy\\Order2'))->toBeNull();
        });

        it('refuses a name that starts with a digit', function () {
            expect(PhpName::classProblem('1'))->toBe('"1" is not a valid class name.');
        });

        it('refuses punctuation PHP could not parse', function () {
            expect(PhpName::classProblem('Foo-Bar'))->toBe('"Foo-Bar" is not a valid class name.');
            expect(PhpName::classProblem('my class'))->toBe('"my class" is not a valid class name.');
        });

        it('refuses a namespace with an empty segment', function () {
            expect(PhpName::classProblem('App\\\\Basket'))->toBe('"App\\\\Basket" is not a valid class name.');
        });

        it('says plainly when nothing was given', function () {
            expect(PhpName::classProblem(''))->toBe('No class name was given.');
            expect(PhpName::classProblem('   '))->toBe('No class name was given.');
        });
    });

    context('method names', function () {
        it('accepts an identifier', function () {
            expect(PhpName::methodProblem('addTask'))->toBeNull();
        });

        it('refuses a name that starts with a digit', function () {
            expect(PhpName::methodProblem('2print'))->toBe('"2print" is not a valid method name.');
        });

        it('refuses a qualified name: a method is one identifier', function () {
            expect(PhpName::methodProblem('App\\Basket::total'))->toBe('"App\\Basket::total" is not a valid method name.');
        });

        it('says plainly when nothing was given', function () {
            expect(PhpName::methodProblem(''))->toBe('No method name was given.');
        });
    });
});
