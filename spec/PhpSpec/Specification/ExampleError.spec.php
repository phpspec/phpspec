<?php

use PhpSpec\Specification\ExampleError;

describe(ExampleError::class, function() {

    it("wraps an exception", function() {
        $original = new \RuntimeException("something broke");
        $error = new ExampleError("something broke", $original);
        expect($error)->toBeAnInstanceOf(ExampleError::class);
        expect($error->getMessage())->toBe("something broke");
    });

    it("knows the error type", function() {
        $original = new \InvalidArgumentException("bad arg");
        $error = new ExampleError("bad arg", $original);
        expect($error->getType())->toBe("InvalidArgumentException");
    });

    it("provides surrounding code", function() {
        $original = new \RuntimeException("fail");
        $error = new ExampleError("fail", $original);
        $code = $error->getSurroundingCode();
        expect(count($code) > 0)->toBe(true);
    });

    it("preserves file and line from original exception", function() {
        $original = new \RuntimeException("fail");
        $error = new ExampleError("fail", $original);
        expect($error->getFile())->toBe(__FILE__);
        expect($error->getLine())->toBeOfType('int');
    });

    it("filters trace frames without file key", function() {
        try {
            array_map(function() { throw new \RuntimeException("no file"); }, [1]);
        } catch (\RuntimeException $e) {
            $error = new ExampleError($e->getMessage(), $e);
            $trace = $error->getFilteredTrace();
            expect($trace)->toBeOfType('array');
        }
    });

    it("filters trace to remove framework and vendor frames", function() {
        $original = new \RuntimeException("fail");
        $error = new ExampleError("fail", $original);
        $trace = $error->getFilteredTrace();
        expect($trace)->toBeOfType('array');

        // No frames should contain src/PhpSpec/ or vendor/
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                expect(str_contains($frame['file'], 'src/PhpSpec/'))->toBe(false);
                expect(str_contains($frame['file'], 'vendor/'))->toBe(false);
            }
        }
    });

    it("names the class a class-not-found error is about", function() {
        $message = 'Class "App\Calculator" not found';
        $error = new ExampleError($message, new \Error($message));
        expect($error->missingClass())->toBe('App\Calculator');
    });

    it("names no class for any other error", function() {
        $error = new ExampleError("something broke", new \RuntimeException("something broke"));
        expect($error->missingClass())->toBeNull();
    });

});
