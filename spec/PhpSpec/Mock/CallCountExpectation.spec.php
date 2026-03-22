<?php

use PhpSpec\Mock\CallCountExpectation;
use PhpSpec\Mock\LastCallDouble;
use PhpSpec\Mock\MethodCallsStack;
use PhpSpec\Mock\MockedMethod;
use PhpSpec\Result\MatchResult;

describe(CallCountExpectation::class, function () {

    it('passes when expected once and called once', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'foo', []));

        $matchable = new LastCallDouble($subject, 'foo');
        $exp = new CallCountExpectation('foo', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->once();
    });

    it('passes when expected twice and called twice', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'bar', []));
        $calls->push(new MockedMethod($subject, 'bar', []));

        $matchable = new LastCallDouble($subject, 'bar');
        $exp = new CallCountExpectation('bar', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->twice();
    });

    it('passes when expected never and not called', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();

        $matchable = new LastCallDouble($subject, 'baz');
        $exp = new CallCountExpectation('baz', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->never();
    });

    it('passes with atMost(2) when called once', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'qux', []));

        $matchable = new LastCallDouble($subject, 'qux');
        $exp = new CallCountExpectation('qux', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->atMost(2)->times();
    });

    it('passes with atLeast(1) when called twice', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'quux', []));
        $calls->push(new MockedMethod($subject, 'quux', []));

        $matchable = new LastCallDouble($subject, 'quux');
        $exp = new CallCountExpectation('quux', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->atLeast(1)->times();
    });

    it('passes with exactly(3)->times()', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'w', []));
        $calls->push(new MockedMethod($subject, 'w', []));
        $calls->push(new MockedMethod($subject, 'w', []));

        $matchable = new LastCallDouble($subject, 'w');
        $exp = new CallCountExpectation('w', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->exactly(3)->times();
    });

    it('passes with negated once() when called twice', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'neg', []));
        $calls->push(new MockedMethod($subject, 'neg', []));

        $matchable = new LastCallDouble($subject, 'neg');
        $exp = new CallCountExpectation('neg', $calls, 'stdClass', __FILE__, __LINE__, true, null, $matchable);
        $exp->once();
    });

    it('passes with negated never() when called once', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'neg2', []));

        $matchable = new LastCallDouble($subject, 'neg2');
        $exp = new CallCountExpectation('neg2', $calls, 'stdClass', __FILE__, __LINE__, true, null, $matchable);
        $exp->never();
    });

    it('passes with setConstraint directly', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'x', []));

        $matchable = new LastCallDouble($subject, 'x');
        $exp = new CallCountExpectation('x', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->setConstraint('exactly', 1);
    });

    it('passes with arg-specific count', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'find', ['alice']));
        $calls->push(new MockedMethod($subject, 'find', ['bob']));

        $matchable = new LastCallDouble($subject, 'find');
        $exp = new CallCountExpectation('find', $calls, 'stdClass', __FILE__, __LINE__, false, ['alice'], $matchable);
        $exp->once();
    });

    it('returns failed result when exactly constraint mismatches', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'foo', []));
        $calls->push(new MockedMethod($subject, 'foo', []));

        $matchable = new LastCallDouble($subject, 'foo');
        // Default is atLeast(1) which passes (2>=1), so deferred eval will succeed
        $exp = new CallCountExpectation('foo', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);

        // Set failing constraint and test via Reflection
        $exp->setConstraint('exactly', 1);
        $ref = new ReflectionMethod($exp, 'evaluate');
        $result = $ref->invoke($exp);
        expect($result->isFailure())->toBeTrue();

        // Reset to passing so deferred callback succeeds
        $exp->setConstraint('exactly', 2);
    });

    it('returns failed result with atLeast constraint message', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();

        $matchable = new LastCallDouble($subject, 'bar');
        // Default atLeast(1), 0 calls → deferred FAILS. Override immediately.
        $exp = new CallCountExpectation('bar', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        // Override to passing default
        $exp->setConstraint('atLeast', 0);

        // Now test failure path via Reflection
        $exp->setConstraint('atLeast', 2);
        $ref = new ReflectionMethod($exp, 'evaluate');
        $result = $ref->invoke($exp);
        expect($result->isFailure())->toBeTrue();

        // Reset to passing so deferred callback succeeds
        $exp->setConstraint('atLeast', 0);
    });

    it('returns failed result with atMost constraint message', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();
        $calls->push(new MockedMethod($subject, 'baz', []));
        $calls->push(new MockedMethod($subject, 'baz', []));
        $calls->push(new MockedMethod($subject, 'baz', []));

        $matchable = new LastCallDouble($subject, 'baz');
        $exp = new CallCountExpectation('baz', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);

        // Test failure path via Reflection
        $exp->setConstraint('atMost', 1);
        $ref = new ReflectionMethod($exp, 'evaluate');
        $result = $ref->invoke($exp);
        expect($result->isFailure())->toBeTrue();

        // Reset to passing
        $exp->setConstraint('atMost', 5);
    });

    it('includes custom constraint label in failure message', function () {
        $calls = new MethodCallsStack();
        $subject = new stdClass();

        $matchable = new LastCallDouble($subject, 'x');
        $exp = new CallCountExpectation('x', $calls, 'stdClass', __FILE__, __LINE__, false, null, $matchable);
        $exp->setConstraint('custom', 5);
        $ref = new ReflectionMethod($exp, 'evaluate');
        $result = $ref->invoke($exp);
        expect($result->isFailure())->toBeTrue();
        expect((string) $result->getMessage())->toContain('custom 5');

        // Reset to passing
        $exp->setConstraint('atLeast', 0);
    });

});
