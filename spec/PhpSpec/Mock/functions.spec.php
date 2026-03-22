<?php

use PhpSpec\Mock\ArgumentMatcher;

describe("Mock global functions", function () {

    it("anInstanceOf returns an ArgumentMatcher", function () {
        $m = anInstanceOf(\stdClass::class);
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches(new \stdClass()))->toBeTrue();
        expect($m->matches("not an object"))->toBeFalse();
    });

    it("arrayIncluding returns an ArgumentMatcher", function () {
        $m = arrayIncluding(['a' => 1]);
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches(['a' => 1, 'b' => 2]))->toBeTrue();
        expect($m->matches(['b' => 2]))->toBeFalse();
    });

    it("satisfy returns an ArgumentMatcher", function () {
        $m = satisfy(fn($v) => $v > 5);
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches(10))->toBeTrue();
        expect($m->matches(3))->toBeFalse();
    });

    it("startWith returns an ArgumentMatcher", function () {
        $m = startWith('hello');
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches('hello world'))->toBeTrue();
        expect($m->matches('world hello'))->toBeFalse();
    });

    it("allow throws when called without preceding mock call", function () {
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        \PhpSpec\Mock\Expectation::$lastCallForAllow = null;

        $thrown = false;
        try {
            allow('not-a-mock-return');
        } catch (LogicException $e) {
            $thrown = true;
            expect($e->getMessage())->toContain('allow() must be called immediately');
        }
        expect($thrown)->toBeTrue();
    });

    it("noArgs returns an ArgumentMatcher", function () {
        $m = noArgs();
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
    });

    it("cetera returns an ArgumentMatcher", function () {
        $m = cetera();
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
    });

    context("injection-style let", function () {
        let(fn(\JsonSerializable $serializer) => null);

        it("injects mocks into let closure parameters", function (\JsonSerializable $serializer) {
            expect($serializer)->toBeAnInstanceOf(\JsonSerializable::class);
        });
    });

    it("mock throws for non-existent class", function () {
        expect(fn() => mock('NonExistent\\FakeClass\\ThatDoesNotExist'))
            ->toThrow(
                \InvalidArgumentException::class,
                "Cannot create mock: class or interface 'NonExistent\\FakeClass\\ThatDoesNotExist' does not exist. "
                . 'Make sure the class is autoloaded or the file is included.'
            );
    });

    it("any returns an ArgumentMatcher", function () {
        $m = any();
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches('anything'))->toBeTrue();
    });

    it("type returns an ArgumentMatcher", function () {
        $m = type('string');
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches('hello'))->toBeTrue();
        expect($m->matches(42))->toBeFalse();
    });

    it("callback returns an ArgumentMatcher", function () {
        $m = callback(fn($v) => $v > 5);
        expect($m)->toBeAnInstanceOf(ArgumentMatcher::class);
        expect($m->matches(10))->toBeTrue();
        expect($m->matches(2))->toBeFalse();
    });

    it("mock creates a double of a class", function () {
        $double = mock(\stdClass::class);
        expect($double)->toBeAnInstanceOf(\stdClass::class);
    });

    it("allow works with MatchableDouble from void method", function (\JsonSerializable $service) {
        // service->jsonSerialize() returns MatchableDouble (no return type)
        $double = mock(\JsonSerializable::class);
        allow($double->jsonSerialize())->toReturn(['key' => 'val']);
        expect($double->jsonSerialize())->toBe(['key' => 'val']);
    });

});
