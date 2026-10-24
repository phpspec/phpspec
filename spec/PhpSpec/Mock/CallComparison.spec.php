<?php

use PhpSpec\Mock\CallComparison;
use PhpSpec\Mock\MethodCallsStack;

describe(CallComparison::class, function () {

    it('reads the wanted call as written, a named argument a variadic collected by its name', function () {
        $comparison = new CallComparison('\App\Journal', 'log', ['hi', 'user' => any()], 'at least 1', new MethodCallsStack());

        expect($comparison->wanted())->toBe(['method' => 'App\Journal::log', 'arguments' => ['hi', 'user' => 'any()'], 'times' => 'at least 1']);
    });
});
