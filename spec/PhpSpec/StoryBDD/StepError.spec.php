<?php

use PhpSpec\StoryBDD\StepError;

describe(StepError::class, function () {

    it("wraps an exception message", function () {
        $ex = new \RuntimeException("step failed");
        $error = new StepError("step failed", $ex);
        expect($error->getMessage())->toBe("step failed");
    });

    it("preserves file and line from original exception", function () {
        $ex = new \RuntimeException("err");
        $error = new StepError("err", $ex);
        expect($error->getFile())->toBe($ex->getFile());
        expect($error->getLine())->toBe($ex->getLine());
    });

    it("returns the error type", function () {
        $ex = new \InvalidArgumentException("bad");
        $error = new StepError("bad", $ex);
        expect($error->getType())->toBe('InvalidArgumentException');
    });

    it("provides surrounding code", function () {
        $ex = new \RuntimeException("err");
        $error = new StepError("err", $ex);
        $code = $error->getSurroundingCode(2, 2);
        expect($code)->toBeOfType('array');
    });

    it("filters trace frames without file key", function () {
        try {
            array_map(function() { throw new \RuntimeException("test"); }, [1]);
        } catch (\RuntimeException $e) {
            $error = new StepError($e->getMessage(), $e);
            $trace = $error->getFilteredTrace();
            expect($trace)->toBeOfType('array');
        }
    });

    it("returns filtered trace without framework frames", function () {
        $ex = new \RuntimeException("err");
        $error = new StepError("err", $ex);
        $trace = $error->getFilteredTrace();
        expect($trace)->toBeOfType('array');
        foreach ($trace as $frame) {
            expect($frame['file'])->not()->toContain('src/PhpSpec/');
            expect($frame['file'])->not()->toContain('vendor/');
        }
    });

});
