<?php

use PhpSpec\Mock\MethodCallsStack;
use PhpSpec\Mock\MockedMethod;

describe(MethodCallsStack::class, function() {

    let("stack", fn() => new MethodCallsStack());

    it("instantiates", fn() => expect($this->stack)->toBeAnInstanceOf(MethodCallsStack::class));

    it("peeks null on empty stack", fn() => expect($this->stack->peek())->toBe(null));

    it("pushes and peeks a method", function() {
        $stack = new MethodCallsStack();
        $method = new MockedMethod(new \stdClass(), "doSomething", []);
        $stack->push($method);
        expect($stack->peek())->toBe($method);
    });

    it("pops a method", function() {
        $stack = new MethodCallsStack();
        $method = new MockedMethod(new \stdClass(), "doSomething", []);
        $stack->push($method);
        expect($stack->pop())->toBe($method);
        expect($stack->peek())->toBe(null);
    });

    it("checks if a method call exists", function() {
        $stack = new MethodCallsStack();
        $method = new MockedMethod(new \stdClass(), "doSomething", ["arg1"]);
        $stack->push($method);
        expect($stack->exist("doSomething", ["arg1"]))->toBe(true);
        expect($stack->exist("doSomething", ["arg2"]))->toBe(false);
        expect($stack->exist("otherMethod", []))->toBe(false);
    });

    it("uncalls only the targeted method", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $doSomething = new MockedMethod($subject, "doSomething", []);
        $doOther = new MockedMethod($subject, "doOther", []);
        $stack->push($doSomething);
        $stack->push($doOther);

        $stack->unCall($doSomething);

        expect($doSomething->wasCalled(0))->toBe(true);
        expect($doOther->wasCalled(1))->toBe(true);
    });

    it("counts calls to a method with countCallsTo", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $stack->push(new MockedMethod($subject, "save", []));
        $stack->push(new MockedMethod($subject, "save", []));
        $stack->push(new MockedMethod($subject, "other", []));
        expect($stack->countCallsTo("save"))->toBe(2);
        expect($stack->countCallsTo("other"))->toBe(1);
        expect($stack->countCallsTo("missing"))->toBe(0);
    });

    it("counts calls filtered by args with countCallsToWithArgs", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $stack->push(new MockedMethod($subject, "find", [1]));
        $stack->push(new MockedMethod($subject, "find", [2]));
        $stack->push(new MockedMethod($subject, "find", [1]));
        expect($stack->countCallsToWithArgs("find", [1]))->toBe(2);
        expect($stack->countCallsToWithArgs("find", [2]))->toBe(1);
        expect($stack->countCallsToWithArgs("find", [3]))->toBe(0);
    });

    it("countCallsToWithArgs with null pattern counts all", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $stack->push(new MockedMethod($subject, "save", [1]));
        $stack->push(new MockedMethod($subject, "save", [2]));
        expect($stack->countCallsToWithArgs("save", null))->toBe(2);
    });

    it("wasMethodCalled returns true when method has positive call count", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $stack->push(new MockedMethod($subject, "save", []));
        expect($stack->wasMethodCalled("save"))->toBe(true);
        expect($stack->wasMethodCalled("missing"))->toBe(false);
    });

    it("wasMethodCalled returns false after uncall", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $method = new MockedMethod($subject, "save", []);
        $stack->push($method);
        $stack->unCall($method);
        expect($stack->wasMethodCalled("save"))->toBe(false);
    });

    it("countCallsToWithArgs with ArgumentMatcher", function() {
        $stack = new MethodCallsStack();
        $subject = new \stdClass();
        $stack->push(new MockedMethod($subject, "store", ["a", 1]));
        $stack->push(new MockedMethod($subject, "store", ["b", 2]));
        expect($stack->countCallsToWithArgs("store", [\PhpSpec\Mock\ArgumentMatcher::type('string'), \PhpSpec\Mock\ArgumentMatcher::any()]))->toBe(2);
    });

});
