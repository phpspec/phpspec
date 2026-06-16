<?php

use PhpSpec\Mock\ArgumentMatcher;
use PhpSpec\Mock\StubRegistry;

describe(StubRegistry::class, function () {

    it('returns null when no stubs are registered', function () {
        $result = StubRegistry::findMatch('foo', [1], [], [], []);
        expect($result)->toBeNull();
    });

    it('matches a catch-all return stub (null args)', function () {
        $stubs = ['foo' => [[null, 'hello']]];
        $result = StubRegistry::findMatch('foo', [42], $stubs, [], []);
        expect($result)->toBe(['type' => 'value', 'data' => 'hello']);
    });

    it('matches an arg-pattern return stub', function () {
        $stubs = ['foo' => [[[1], 'one'], [[2], 'two']]];
        $result = StubRegistry::findMatch('foo', [2], $stubs, [], []);
        expect($result)->toBe(['type' => 'value', 'data' => 'two']);
    });

    it('returns null for non-matching args', function () {
        $stubs = ['foo' => [[[1], 'one']]];
        $result = StubRegistry::findMatch('foo', [99], $stubs, [], []);
        expect($result)->toBeNull();
    });

    it('later-registered stubs take priority (first in array = prepended = latest)', function () {
        // array_unshift means latest is first
        $stubs = ['foo' => [[[1], 'latest'], [[1], 'older']]];
        $result = StubRegistry::findMatch('foo', [1], $stubs, [], []);
        expect($result)->toBe(['type' => 'value', 'data' => 'latest']);
    });

    it('specific pattern takes priority over catch-all', function () {
        // Specific is prepended (first), catch-all is second
        $stubs = ['foo' => [[[42], 'special'], [null, 'default']]];
        $result = StubRegistry::findMatch('foo', [42], $stubs, [], []);
        expect($result)->toBe(['type' => 'value', 'data' => 'special']);
    });

    it('falls back to catch-all when specific does not match', function () {
        $stubs = ['foo' => [[[42], 'special'], [null, 'default']]];
        $result = StubRegistry::findMatch('foo', [99], $stubs, [], []);
        expect($result)->toBe(['type' => 'value', 'data' => 'default']);
    });

    it('matches a callback stub', function () {
        $fn = fn($x) => $x * 2;
        $callbacks = ['foo' => [[[1], $fn]]];
        $result = StubRegistry::findMatch('foo', [1], [], $callbacks, []);
        expect($result['type'])->toBe('callback');
        expect($result['data'])->toBe($fn);
    });

    it('matches a throw stub', function () {
        $throwData = ['class' => \RuntimeException::class, 'message' => 'boom'];
        $throws = ['foo' => [[[1], $throwData]]];
        $result = StubRegistry::findMatch('foo', [1], [], [], $throws);
        expect($result)->toBe(['type' => 'throw', 'data' => $throwData]);
    });

    it('throws take priority over callbacks and values for same args', function () {
        $throwData = ['class' => \RuntimeException::class, 'message' => 'boom'];
        $stubs = ['foo' => [[null, 'val']]];
        $callbacks = ['foo' => [[null, fn() => 'cb']]];
        $throws = ['foo' => [[null, $throwData]]];
        $result = StubRegistry::findMatch('foo', [], $stubs, $callbacks, $throws);
        expect($result['type'])->toBe('throw');
    });

    it('returns null for a different method name', function () {
        $stubs = ['foo' => [[null, 'hello']]];
        $result = StubRegistry::findMatch('bar', [1], $stubs, [], []);
        expect($result)->toBeNull();
    });

    it('supports ArgumentMatcher in patterns', function () {
        $stubs = ['foo' => [[[ArgumentMatcher::type('string')], 'matched']]];
        $result = StubRegistry::findMatch('foo', ['hello'], $stubs, [], []);
        expect($result)->toBe(['type' => 'value', 'data' => 'matched']);
    });

});
