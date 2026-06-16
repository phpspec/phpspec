<?php

use PhpSpec\Mock\ArgumentMatcher;

describe(ArgumentMatcher::class, function () {

    describe('any()', function () {
        it('matches any value', function () {
            $m = ArgumentMatcher::any();
            expect($m->matches('hello'))->toBeTrue();
            expect($m->matches(42))->toBeTrue();
            expect($m->matches(null))->toBeTrue();
        });
    });

    describe('type()', function () {
        it('matches by built-in type name', function () {
            expect(ArgumentMatcher::type('string')->matches('hello'))->toBeTrue();
            expect(ArgumentMatcher::type('int')->matches(42))->toBeTrue();
            expect(ArgumentMatcher::type('string')->matches(42))->toBeFalse();
        });

        it('matches by class name', function () {
            expect(ArgumentMatcher::type('stdClass')->matches(new stdClass()))->toBeTrue();
            expect(ArgumentMatcher::type('stdClass')->matches('nope'))->toBeFalse();
        });
    });

    describe('callback()', function () {
        it('matches when callback returns true', function () {
            $m = ArgumentMatcher::callback(fn($v) => $v > 10);
            expect($m->matches(20))->toBeTrue();
            expect($m->matches(5))->toBeFalse();
        });
    });

    describe('noArgs()', function () {
        it('reports isNoArgs true', function () {
            expect(ArgumentMatcher::noArgs()->isNoArgs())->toBeTrue();
        });

        it('is not cetera', function () {
            expect(ArgumentMatcher::noArgs()->isCetera())->toBeFalse();
        });
    });

    describe('cetera()', function () {
        it('reports isCetera true', function () {
            expect(ArgumentMatcher::cetera()->isCetera())->toBeTrue();
        });

        it('is not noArgs', function () {
            expect(ArgumentMatcher::cetera()->isNoArgs())->toBeFalse();
        });

        it('matches() returns true for any value', function () {
            expect(ArgumentMatcher::cetera()->matches('anything'))->toBeTrue();
        });
    });

    describe('instanceOf()', function () {
        it('matches objects of the given class', function () {
            expect(ArgumentMatcher::instanceOf('stdClass')->matches(new stdClass()))->toBeTrue();
        });

        it('rejects objects of a different class', function () {
            expect(ArgumentMatcher::instanceOf('stdClass')->matches(new ArrayObject()))->toBeFalse();
        });

        it('rejects non-objects', function () {
            expect(ArgumentMatcher::instanceOf('stdClass')->matches('string'))->toBeFalse();
        });
    });

    describe('arrayIncluding()', function () {
        it('matches array containing the subset', function () {
            $m = ArgumentMatcher::arrayIncluding(['a' => 1]);
            expect($m->matches(['a' => 1, 'b' => 2]))->toBeTrue();
        });

        it('rejects array missing a key', function () {
            $m = ArgumentMatcher::arrayIncluding(['x' => 1]);
            expect($m->matches(['a' => 1]))->toBeFalse();
        });

        it('rejects array with wrong value', function () {
            $m = ArgumentMatcher::arrayIncluding(['a' => 1]);
            expect($m->matches(['a' => 2]))->toBeFalse();
        });

        it('rejects non-array', function () {
            $m = ArgumentMatcher::arrayIncluding(['a' => 1]);
            expect($m->matches('string'))->toBeFalse();
        });
    });

    describe('satisfy()', function () {
        it('works like callback', function () {
            $m = ArgumentMatcher::satisfy(fn($v) => $v === 'yes');
            expect($m->matches('yes'))->toBeTrue();
            expect($m->matches('no'))->toBeFalse();
        });
    });

    describe('startWith()', function () {
        it('matches strings starting with prefix', function () {
            expect(ArgumentMatcher::startWith('foo')->matches('foobar'))->toBeTrue();
        });

        it('rejects strings not starting with prefix', function () {
            expect(ArgumentMatcher::startWith('foo')->matches('barfoo'))->toBeFalse();
        });

        it('rejects non-strings', function () {
            expect(ArgumentMatcher::startWith('foo')->matches(42))->toBeFalse();
        });
    });

    describe('matchArgs()', function () {
        it('matches exact values', function () {
            expect(ArgumentMatcher::matchArgs([1, 'a'], [1, 'a']))->toBeTrue();
        });

        it('rejects different values', function () {
            expect(ArgumentMatcher::matchArgs([1, 'a'], [1, 'b']))->toBeFalse();
        });

        it('rejects different lengths', function () {
            expect(ArgumentMatcher::matchArgs([1], [1, 2]))->toBeFalse();
        });

        it('handles noArgs with empty actual', function () {
            expect(ArgumentMatcher::matchArgs([], [ArgumentMatcher::noArgs()]))->toBeTrue();
        });

        it('handles noArgs with non-empty actual', function () {
            expect(ArgumentMatcher::matchArgs([1], [ArgumentMatcher::noArgs()]))->toBeFalse();
        });

        it('handles cetera at end', function () {
            expect(ArgumentMatcher::matchArgs(
                ['info', 'hello', 'extra'],
                ['info', ArgumentMatcher::cetera()]
            ))->toBeTrue();
        });

        it('handles cetera matching zero trailing args', function () {
            expect(ArgumentMatcher::matchArgs(
                ['info'],
                ['info', ArgumentMatcher::cetera()]
            ))->toBeTrue();
        });

        it('handles mixed matchers and literals', function () {
            expect(ArgumentMatcher::matchArgs(
                ['hello', 42],
                [ArgumentMatcher::type('string'), 42]
            ))->toBeTrue();
        });

        it('rejects when matcher fails', function () {
            expect(ArgumentMatcher::matchArgs(
                [42, 'x'],
                [ArgumentMatcher::type('string'), 'x']
            ))->toBeFalse();
        });
    });

});
