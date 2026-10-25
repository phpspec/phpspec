<?php

use PhpSpec\Mock\ArgumentMatcher;

interface ArgumentMatcherSpecPage {
    public function next(): ArgumentMatcherSpecPage;
}

interface ArgumentMatcherSpecBook {
    public function first(): ArgumentMatcherSpecPage;

    public function last(): ArgumentMatcherSpecPage;
}

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

    describe('cetera()', function () {
        it('reports isCetera true', function () {
            expect(ArgumentMatcher::cetera()->isCetera())->toBeTrue();
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

        it('tells two doubles of one type apart', function (ArgumentMatcherSpecPage $first, ArgumentMatcherSpecPage $second) {
            $m = ArgumentMatcher::arrayIncluding(['page' => $first]);
            expect($m->matches(['page' => $second, 'at' => 1]))->toBeFalse();
            expect($m->matches(['page' => $first, 'at' => 1]))->toBeTrue();
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

    describe('matchArgs() with objects', function () {
        it('matches an equal object, not only the very same instance', function () {
            expect(ArgumentMatcher::matchArgs([new ArrayObject([1])], [new ArrayObject([1])]))->toBeTrue();
        });

        it('tells objects of a different state apart', function () {
            expect(ArgumentMatcher::matchArgs([new ArrayObject([1])], [new ArrayObject([2])]))->toBeFalse();
        });

        it('matches equal objects inside an array argument', function () {
            expect(ArgumentMatcher::matchArgs([['a' => new ArrayObject([1])]], [['a' => new ArrayObject([1])]]))->toBeTrue();
        });

        it('keeps scalars strict', function () {
            expect(ArgumentMatcher::matchArgs(['1'], [1]))->toBeFalse();
        });
    });

    describe('matchArgs() with doubles', function () {
        it('tells two doubles of one type apart', function (ArgumentMatcherSpecPage $first, ArgumentMatcherSpecPage $second) {
            expect(ArgumentMatcher::matchArgs([$second], [$first]))->toBeFalse();
        });

        it('matches a double with itself', function (ArgumentMatcherSpecPage $first) {
            expect(ArgumentMatcher::matchArgs([$first], [$first]))->toBeTrue();
        });

        it('tells two doubles of one type apart inside an array argument', function (ArgumentMatcherSpecPage $first, ArgumentMatcherSpecPage $second) {
            expect(ArgumentMatcher::matchArgs([['page' => $second]], [['page' => $first]]))->toBeFalse();
            expect(ArgumentMatcher::matchArgs([['page' => $first]], [['page' => $first]]))->toBeTrue();
        });

        it('tells apart two dummies, and two answers of doubles, of one type', function () {
            expect(ArgumentMatcher::matchArgs([dummy(ArgumentMatcherSpecPage::class)], [dummy(ArgumentMatcherSpecPage::class)]))->toBeFalse();
            expect(ArgumentMatcher::matchArgs([dummy(ArgumentMatcherSpecBook::class)->first()], [dummy(ArgumentMatcherSpecBook::class)->first()]))->toBeFalse();
            expect(ArgumentMatcher::matchArgs([mock(ArgumentMatcherSpecBook::class)->first()], [mock(ArgumentMatcherSpecBook::class)->first()]))->toBeFalse();
        });

        it('matches what a double answered to a method with what it answers to that method again', function () {
            $dummy = dummy(ArgumentMatcherSpecBook::class);
            $mock = mock(ArgumentMatcherSpecBook::class);

            expect(ArgumentMatcher::matchArgs([$dummy->first()], [$dummy->first()]))->toBeTrue();
            expect(ArgumentMatcher::matchArgs([$mock->first()], [$mock->first()]))->toBeTrue();
        });

        it('tells apart what a double answered to two methods', function () {
            $book = dummy(ArgumentMatcherSpecBook::class);

            expect(ArgumentMatcher::matchArgs([$book->first()], [$book->last()]))->toBeFalse();
        });

        it('follows a chain of answers back to the double it started from', function () {
            $book = dummy(ArgumentMatcherSpecBook::class);

            expect(ArgumentMatcher::matchArgs([$book->first()->next()], [$book->first()->next()]))->toBeTrue();
            expect(ArgumentMatcher::matchArgs([$book->first()->next()], [dummy(ArgumentMatcherSpecBook::class)->first()->next()]))->toBeFalse();
            expect(ArgumentMatcher::matchArgs([$book->first()->next()], [$book->last()->next()]))->toBeFalse();
        });

        it('takes what a double answered for no double the spec made', function (ArgumentMatcherSpecPage $page) {
            $book = dummy(ArgumentMatcherSpecBook::class);

            expect(ArgumentMatcher::matchArgs([$book->first()], [$page]))->toBeFalse();
            expect(ArgumentMatcher::matchArgs([$page], [$book->first()]))->toBeFalse();
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

    describe('describe()', function () {
        it('describes itself the way it is written in a spec', function () {
            expect(ArgumentMatcher::any()->describe())->toBe('any()');
            expect(ArgumentMatcher::type('string')->describe())->toBe('type(string)');
            expect(ArgumentMatcher::instanceOf('App\\Task')->describe())->toBe('anInstanceOf(App\\Task)');
            expect(ArgumentMatcher::startWith('pre')->describe())->toBe('startWith("pre")');
            expect(ArgumentMatcher::arrayIncluding(['a' => 1])->describe())->toBe('arrayIncluding({"a":1})');
            expect(ArgumentMatcher::callback(fn() => true)->describe())->toBe('callback(...)');
            expect(ArgumentMatcher::cetera()->describe())->toBe('cetera()');
        });
    });


    it('matches a matcher given as an argument only when it is the same matcher, never running its predicate', function () {
        $typed = ArgumentMatcher::satisfy(fn (int $value) => $value > 1);

        expect($typed->matches(ArgumentMatcher::satisfy(fn (int $value) => $value > 2)))->toBeFalse();
        expect($typed->matches($typed))->toBeTrue();
        expect(ArgumentMatcher::type('string')->matches(ArgumentMatcher::type('string')))->toBeTrue();
        expect(ArgumentMatcher::any()->matches(ArgumentMatcher::type('string')))->toBeFalse();
    });

    it('matches a matcher given as an argument only when it holds the very doubles it holds', function (ArgumentMatcherSpecPage $first, ArgumentMatcherSpecPage $second) {
        $including = ArgumentMatcher::arrayIncluding(['page' => $first]);

        expect($including->matches(ArgumentMatcher::arrayIncluding(['page' => $second])))->toBeFalse();
        expect($including->matches(ArgumentMatcher::arrayIncluding(['page' => $first])))->toBeTrue();
    });

    it('compares matchers nested in a matcher by the same rules, never running a predicate', function (ArgumentMatcherSpecPage $first, ArgumentMatcherSpecPage $second) {
        $positive = fn (int $value) => $value > 0;
        $nested = ArgumentMatcher::arrayIncluding(['in' => ArgumentMatcher::arrayIncluding(['page' => $first]), 'check' => ArgumentMatcher::satisfy($positive)]);

        expect($nested->matches(ArgumentMatcher::arrayIncluding(['in' => ArgumentMatcher::arrayIncluding(['page' => $second]), 'check' => ArgumentMatcher::satisfy($positive)])))->toBeFalse();
        expect($nested->matches(ArgumentMatcher::arrayIncluding(['in' => ArgumentMatcher::arrayIncluding(['page' => $first]), 'check' => ArgumentMatcher::satisfy($positive)])))->toBeTrue();
        expect($nested->matches(ArgumentMatcher::arrayIncluding(['in' => ArgumentMatcher::arrayIncluding(['page' => $first]), 'check' => ArgumentMatcher::satisfy(fn (int $value) => $value > 1)])))->toBeFalse();
        expect(ArgumentMatcher::type('stdClass')->matches(ArgumentMatcher::instanceOf('stdClass')))->toBeFalse();
    });
});
