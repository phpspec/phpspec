<?php

use PhpSpec\Specification\Expectation;

describe(Expectation::class, function () {

    context('formatMessage', function () {

        it('formats message with object value', function () {
            $method = new \ReflectionMethod(Expectation::class, 'formatMessage');
            $obj = new \stdClass();
            $result = $method->invoke(null, 'Expected %s', $obj);
            expect($result)->toContain('stdClass');
        });

        it('formats message with boolean value', function () {
            $method = new \ReflectionMethod(Expectation::class, 'formatMessage');
            $result = $method->invoke(null, 'Expected %s', true);
            expect($result)->toContain('true');
        });

        it('formats message with array value', function () {
            $method = new \ReflectionMethod(Expectation::class, 'formatMessage');
            $result = $method->invoke(null, 'Expected %s', [1, 2, 3]);
            expect($result)->toContain('[1, 2, 3]');
        });

        it('formats message with integer value (default branch)', function () {
            $method = new \ReflectionMethod(Expectation::class, 'formatMessage');
            $result = $method->invoke(null, 'Expected %s', 42);
            expect($result)->toContain('42');
        });
    });
});
