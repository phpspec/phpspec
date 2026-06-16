<?php

use PhpSpec\CodeGeneration\SurroundingCode;
use PhpSpec\Result\Detail;
use PhpSpec\Result\Detail\Failed;
use PhpSpec\Result\Detail\Successful;

describe("Detail classes", function() {

    context("Successful", function() {
        it("is successful and has no failure", function() {
            $detail = Successful::matcher();
            expect($detail->isSuccessful())->toBe(true);
            expect($detail->isFailure())->toBe(false);
            expect($detail->getFailure())->toBe(Detail::Nothing);
            expect($detail->getSurroundingCode())->toBe([]);
            expect($detail->getLine())->toBeNull();
            expect($detail->getFile())->toBeNull();
        });
    });

    context("Failed", function() {
        it("carries expected, actual, message, code, line, and file", function() {
            $failed = Failed::matcher(
                "expected_val",
                "actual_val",
                "Expected expected_val to be actual_val",
                new SurroundingCode(__FILE__, __LINE__),
                42,
                "/path/to/file.php"
            );

            expect($failed->isSuccessful())->toBe(false);
            expect($failed->isFailure())->toBe(true);
            expect($failed->getFailure())->toBe("Expected expected_val to be actual_val");
            expect($failed->getExpected())->toBe("expected_val");
            expect($failed->getActual())->toBe("actual_val");
            expect($failed->getLine())->toBe(42);
            expect($failed->getFile())->toBe("/path/to/file.php");
            expect($failed->getSurroundingCode())->toBeOfType('array');
            expect($failed->getFakeExpression())->toBeNull();
        });

        it("carries a fake expression when provided", function() {
            $failed = Failed::matcher(
                "x", "y", "msg",
                new SurroundingCode(__FILE__, __LINE__),
                1, __FILE__, "'hello'"
            );
            expect($failed->getFakeExpression())->toBe("'hello'");
        });
    });

});
