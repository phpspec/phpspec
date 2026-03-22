<?php

use PhpSpec\Result\MatchResult;
use PhpSpec\Result\Result;

describe(MatchResult::class, function() {

    it("creates a passed result", function() {
        $result = MatchResult::passed();
        expect($result)->toBeAnInstanceOf(MatchResult::class);
        expect($result->getResult())->toBe(Result::Passed);
        expect($result->isFailure())->toBe(false);
        expect($result->getMessage())->toBe(MatchResult::passed()->getMessage());
        expect($result->getCode())->toBe([]);
        expect($result->getLine())->toBeNull();
        expect($result->getFile())->toBeNull();
        expect($result->getExpected())->toBeNull();
        expect($result->getActual())->toBeNull();
        expect($result->getFakeExpression())->toBeNull();
    });

    it("creates a failed result", function() {
        $result = MatchResult::failed("expected", "actual", "Expected expected to be actual", __FILE__, __LINE__);
        expect($result)->toBeAnInstanceOf(MatchResult::class);
        expect($result->getResult())->toBe(Result::Failed);
        expect($result->isFailure())->toBe(true);
        expect($result->getMessage())->toBe("Expected expected to be actual");
        expect($result->getLine())->toBeOfType('int');
        expect($result->getFile())->toBe(__FILE__);
        expect($result->getExpected())->toBe("expected");
        expect($result->getActual())->toBe("actual");
        expect($result->getCode())->toBeOfType('array');
    });

    it("returns null fake expression when not provided", function() {
        $result = MatchResult::failed("a", "b", "msg", __FILE__, __LINE__);
        expect($result->getFakeExpression())->toBeNull();
    });

    it("carries fake expression when provided", function() {
        $result = MatchResult::failed("a", "b", "msg", __FILE__, __LINE__, "'hello'");
        expect($result->getFakeExpression())->toBe("'hello'");
    });

    it("returns empty results array", function() {
        $result = MatchResult::passed();
        expect($result->getResults())->toBe([]);
    });

    it("returns expected value from failed result", function() {
        $result = MatchResult::failed("expected_val", "actual_val", "msg", __FILE__, __LINE__);
        expect($result->getExpected())->toBe("expected_val");
        expect($result->getActual())->toBe("actual_val");
    });

    it("returns null expected/actual for passed result", function() {
        $result = MatchResult::passed();
        expect($result->getExpected())->toBeNull();
        expect($result->getActual())->toBeNull();
        expect($result->getFakeExpression())->toBeNull();
    });

});
