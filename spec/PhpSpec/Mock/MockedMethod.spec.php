<?php

use PhpSpec\Mock\MockedMethod;

describe(MockedMethod::class, function() {

    let("subject", fn() => new \stdClass());
    let("method", fn() => new MockedMethod($this->subject, "doSomething", ["arg1"]));

    it("instantiates", fn() => expect($this->method)->toBeAnInstanceOf(MockedMethod::class));

    it("starts with one call", fn() => expect($this->method->wasCalled(1))->toBe(true));

    it("can be uncalled", function() {
        $method = new MockedMethod(new \stdClass(), "doSomething", []);
        $method->unCall();
        expect($method->wasCalled(0))->toBe(true);
    });

    it("can be called again", function() {
        $method = new MockedMethod(new \stdClass(), "doSomething", []);
        $method->call();
        expect($method->wasCalled(2))->toBe(true);
    });

    it("can be created without a call", function() {
        $method = MockedMethod::withoutCall($this->subject, "doSomething");
        expect($method->wasCalled(0))->toBe(true);
    });

    it("getTimesCalled returns the count", function() {
        $method = new MockedMethod(new \stdClass(), "test", []);
        expect($method->getTimesCalled())->toBe(1);
        $method->call();
        expect($method->getTimesCalled())->toBe(2);
        $method->unCall();
        expect($method->getTimesCalled())->toBe(1);
    });

    it("withoutCall starts at zero and can be called", function() {
        $method = MockedMethod::withoutCall(new \stdClass(), "test", ["a", "b"]);
        expect($method->getTimesCalled())->toBe(0);
        expect($method->wasCalled(0))->toBe(true);
        $method->call();
        expect($method->getTimesCalled())->toBe(1);
        expect($method->wasCalled(1))->toBe(true);
    });

    it("exposes method name and arguments", function() {
        $subject = new \stdClass();
        $method = new MockedMethod($subject, "doSomething", ["a", 1]);
        expect($method->method)->toBe("doSomething");
        expect($method->arguments)->toBe(["a", 1]);
        expect($method->subject)->toBe($subject);
    });

});
