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

});
