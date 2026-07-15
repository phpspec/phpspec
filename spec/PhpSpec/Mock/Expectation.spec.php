<?php

use PhpSpec\Mock\Expectation;

interface ExpectationSpecService {
    public function process(): string;
    public function save();
    public function store(string $key, int $value);
    public function count(): int;
}

class ExpectationSpecConsumer {
    public function __construct(private ExpectationSpecService $service) {}

    public function handle() {
        $this->service->process();
        $this->service->save();
    }
}

describe(Expectation::class, function() {

    it("inherits standard matchers from base Expectation", function(ExpectationSpecService $service) {
        expect($service)->toBeAnInstanceOf(ExpectationSpecService::class);
    });

    it("verifies a method was called with toBeCalled", function(ExpectationSpecService $service) {
        $consumer = new ExpectationSpecConsumer($service);
        expect($service->save())->toBeCalled();
        $consumer->handle();
    });

    it("verifies a typed method was called with toBeCalled", function(ExpectationSpecService $service) {
        $consumer = new ExpectationSpecConsumer($service);
        expect($service->process())->toBeCalled();
        $consumer->handle();
    });

    it("verifies with toHaveBeenCalled alias", function(ExpectationSpecService $service) {
        $consumer = new ExpectationSpecConsumer($service);
        expect($service->save())->toHaveBeenCalled();
        $consumer->handle();
    });

    it("verifies with toBeCalledWith using exact args", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->toBeCalledWith('foo', 42);
        $service->store('foo', 42);
    });

    it("verifies with toBeCalledWith using any() matcher", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->toBeCalledWith(any(), 42);
        $service->store('bar', 42);
    });

    it("verifies with toBeCalledWith using type() matcher", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->toBeCalledWith(type('string'), type('int'));
        $service->store('hello', 99);
    });

    it("verifies with toBeCalledWith using callback() matcher", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->toBeCalledWith(
            callback(fn($v) => strlen($v) > 2),
            any()
        );
        $service->store('hello', 1);
    });

    it("verifies toBeCalledTimes", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalledTimes(3);
        $service->save();
        $service->save();
        $service->save();
    });

    it("stubs return value with allow", function(ExpectationSpecService $service) {
        allow($service->process())->toReturn("hello");
        expect($service->process())->toBe("hello");
    });

    it("stubs exception with allow", function(ExpectationSpecService $service) {
        allow($service->save())->toThrow(\RuntimeException::class, "boom");
        expect(fn() => $service->save())->toThrow(\RuntimeException::class, "boom");
    });

    it("supports not()->toBeCalled()", function(ExpectationSpecService $service) {
        expect($service->save())->not()->toBeCalled();
        // Don't call save
    });

    it("stubs return value via allow()->toReturn()", function(ExpectationSpecService $service) {
        allow($service->count())->toReturn(42);
        expect($service->count())->toBe(42);
    });

    it("stubs return value via allow()->toReturnUsing()", function(ExpectationSpecService $service) {
        allow($service->count())->toReturnUsing(fn() => 99);
        expect($service->count())->toBe(99);
    });

    it("throws when allow() is called without a preceding mock call", function() {
        \PhpSpec\Mock\Expectation::$lastCallForAllow = null;
        expect(fn() => allow(null))->toThrow(\LogicException::class);
    });

    it("stubs via toReturn on LastCallDouble for void method", function(ExpectationSpecService $service) {
        $service->save()->toReturn('stubbed');
        $result = $service->save();
        expect($result)->toBe('stubbed');
    });

    it("stubs via toThrow on LastCallDouble for void method", function(ExpectationSpecService $service) {
        $service->save()->toThrow(\LogicException::class, 'nope');
        expect(fn() => $service->save())->toThrow(\LogicException::class, 'nope');
    });

    it("stubs exception with allow via scalar-return method", function(ExpectationSpecService $service) {
        allow($service->process())->toThrow(\RuntimeException::class, "scalar-throw");
        expect(fn() => $service->process())->toThrow(\RuntimeException::class, "scalar-throw");
    });

    it("exposes the method name via LastCallDouble", function(ExpectationSpecService $service) {
        $lcd = new \PhpSpec\Mock\LastCallDouble($service, 'process');
        expect($lcd->______PhpSpecGetMethod())->toBe('process');
    });

    it("negated toBeCalledWith with wrong arg count", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->not()->toBeCalledWith('bar');
        $service->store('baz', 100);
    });

    it("negated toBeCalledWith with different values", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->not()->toBeCalledWith('wrong', 99);
        $service->store('other', 50);
    });

    it("negated toBeCalledWith with failing type matcher", function(ExpectationSpecService $service) {
        expect($service->store('foo', 42))->not()->toBeCalledWith(type('int'), any());
        $service->store('hello', 1);
    });

    it("formats message with object value via formatMessage", function(ExpectationSpecService $service) {
        $md = $service->save();
        $exp = new \PhpSpec\Mock\Expectation($md, __FILE__, __LINE__);
        $method = new \ReflectionMethod($exp, 'formatMessage');
        $obj = new \stdClass();
        $result = $method->invoke($exp, "Expected %s", $obj);
        expect($result)->toContain("stdClass");
    });

    it("formats message with string value via formatMessage", function(ExpectationSpecService $service) {
        $md = $service->save();
        $exp = new \PhpSpec\Mock\Expectation($md, __FILE__, __LINE__);
        $method = new \ReflectionMethod($exp, 'formatMessage');
        $result = $method->invoke($exp, "Expected %s", "hello");
        expect($result)->toContain('"hello"');
    });

    it("formats message with boolean value via formatMessage", function(ExpectationSpecService $service) {
        $md = $service->save();
        $exp = new \PhpSpec\Mock\Expectation($md, __FILE__, __LINE__);
        $method = new \ReflectionMethod($exp, 'formatMessage');
        $result = $method->invoke($exp, "Expected %s", false);
        expect($result)->toContain("false");
    });

    it("formats message with array value via formatMessage", function(ExpectationSpecService $service) {
        $md = $service->save();
        $exp = new \PhpSpec\Mock\Expectation($md, __FILE__, __LINE__);
        $method = new \ReflectionMethod($exp, 'formatMessage');
        $result = $method->invoke($exp, "Expected %s", [1, 2]);
        expect($result)->toContain("[1, 2]");
    });

    it("formats message with null value via formatMessage", function(ExpectationSpecService $service) {
        $md = $service->save();
        $exp = new \PhpSpec\Mock\Expectation($md, __FILE__, __LINE__);
        $method = new \ReflectionMethod($exp, 'formatMessage');
        $result = $method->invoke($exp, "Expected %s", null);
        expect($result)->toContain("null");
    });

    it("stubs return value via expect()->toReturn()", function(ExpectationSpecService $service) {
        expect($service->process())->toReturn("stubbed-via-expect");
        expect($service->process())->toBe("stubbed-via-expect");
    });

    it("stubs exception via expect()->toThrow()", function(ExpectationSpecService $service) {
        expect($service->save())->toThrow(\RuntimeException::class, "expect-throw");
        expect(fn() => $service->save())->toThrow(\RuntimeException::class, "expect-throw");
    });

    it("toBeCalled()->once() passes with one call", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled()->once();
        $service->save();
    });

    it("toBeCalled()->twice() passes with two calls", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled()->twice();
        $service->save();
        $service->save();
    });

    it("toBeCalled()->exactly(3)->times() passes with three calls", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled()->exactly(3)->times();
        $service->save();
        $service->save();
        $service->save();
    });

    it("toBeCalled()->never() passes with zero calls", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled()->never();
    });

    it("toBeCalled()->atLeast(2)->times() passes with three calls", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled()->atLeast(2)->times();
        $service->save();
        $service->save();
        $service->save();
    });

    it("toBeCalled()->atMost(2)->times() passes with one call", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled()->atMost(2)->times();
        $service->save();
    });

    it("bare toBeCalled() still works (at least once)", function(ExpectationSpecService $service) {
        expect($service->save())->toBeCalled();
        $service->save();
    });

    it("toHaveBeenCalled()->once() works as alias", function(ExpectationSpecService $service) {
        expect($service->save())->toHaveBeenCalled()->once();
        $service->save();
    });

    it("stubs different return values for different args", function(ExpectationSpecService $service) {
        allow($service->store('a', 1))->toReturn(null);
        allow($service->store('b', 2))->toReturn(null);
        allow($service->count())->toReturn(10);
        // count() should still return 10
        expect($service->count())->toBe(10);
    });

    it("stubs with any() matcher in allow call", function(ExpectationSpecService $service) {
        allow($service->store(any(), any()))->toReturn(null);
        allow($service->store('special', 42))->toReturn(null);
        expect($service->store('special', 42))->toBeNull();
    });

    it("formats message with integer value via formatMessage", function(ExpectationSpecService $service) {
        $md = $service->save();
        $exp = new \PhpSpec\Mock\Expectation($md, __FILE__, __LINE__);
        $method = new \ReflectionMethod($exp, 'formatMessage');
        $result = $method->invoke($exp, "Expected %s", 42);
        expect($result)->toContain("42");
    });

});
