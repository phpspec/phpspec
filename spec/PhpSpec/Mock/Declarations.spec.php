<?php

use PhpSpec\Mock\ArgumentMatcher;
use PhpSpec\Mock\Declarations;

describe(Declarations::class, function () {
    it('covers a call declared with its arguments, a matcher standing for values', function () {
        $declarations = new Declarations();
        $declarations->add('reserve', ['tea', ArgumentMatcher::any()]);

        expect($declarations->cover('reserve', ['tea', 2]))->toBeTrue();
        expect($declarations->cover('reserve', ['coffee', 2]))->toBeFalse();
        expect($declarations->cover('audit', ['tea']))->toBeFalse();
    });

    it('covers any call to a method declared without knowing its arguments', function () {
        $declarations = new Declarations();
        $declarations->add('reserve', null);

        expect($declarations->cover('reserve', ['anything', 9]))->toBeTrue();
    });
});
