<?php

use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Specification\ExampleError;

describe(ContextResult::class, function() {

    it("reports its own error on its last result, and only when it broke", function() {
        $ran = new ExampleResult("answers", []);
        $carrier = new ExampleResult("Server", [], true);
        $broken = new ContextResult("Server", [$ran, $carrier]);
        $broken->setError(new ExampleError("would not stop", new \RuntimeException("would not stop")));
        $whole = new ContextResult("Server", [$ran, $carrier]);

        expect([$broken->carriesItsError($ran), $broken->carriesItsError($carrier), $whole->carriesItsError($carrier)])->toBe([false, true, false]);
    });

    it("tracks its title", function() {
        $result = new ContextResult("my context", []);
        expect($result->getTitle())->toBe("my context");
    });

    it("is a context", function() {
        $result = new ContextResult("ctx", []);
        expect($result->isContext())->toBe(true);
    });

    it("holds example results", function() {
        $example = new ExampleResult("test", []);
        $result = new ContextResult("ctx", [$example]);
        expect($result->getResults())->toHaveCount(1);
    });

    it("tracks errors", function() {
        $result = new ContextResult("ctx", []);
        expect($result->isError())->toBe(false);

        $error = new ExampleError("broke", new \RuntimeException("broke"));
        $result->setError($error);
        expect($result->isError())->toBe(true);
    });

    it("returns the error object", function() {
        $result = new ContextResult("ctx", []);
        $error = new ExampleError("broke", new \RuntimeException("broke"));
        $result->setError($error);
        expect($result->getError())->toBeAnInstanceOf(ExampleError::class);
        expect($result->getError()->getMessage())->toBe("broke");
    });

});
