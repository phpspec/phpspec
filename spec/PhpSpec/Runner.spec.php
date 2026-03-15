<?php

use PhpSpec\Runner;
use PhpSpec\Suite;
use PhpSpec\Result\SuiteResult;

describe(Runner::class, function() {
    let("runner", fn() => new Runner());

    it("instantiates", fn() =>
        expect($this->runner)->toBeAnInstanceOf(Runner::class));

    it("delegates run to the suite", function() {
        $suite = new Suite([]);
        $result = $this->runner->run($suite);
        expect($result)->toBeAnInstanceOf(SuiteResult::class);
    });
});
