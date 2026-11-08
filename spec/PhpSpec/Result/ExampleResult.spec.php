<?php

use PhpSpec\ProjectRoot;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\Kind;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\State;
use PhpSpec\Specification\ExampleError;

describe(ExampleResult::class, function() {

    it("is not a failure with passing matches", function() {
        $match = MatchResult::passed();
        $result = new ExampleResult("test", [$match]);
        expect($result->isFailure())->toBe(false);
        expect($result->isError())->toBe(false);
    });

    it("is a failure when a match fails", function() {
        $passing = MatchResult::passed();
        $failing = MatchResult::failed("a", "b", "fail", __FILE__, __LINE__);

        $result = new ExampleResult("test", [$passing, $failing]);
        expect($result->isFailure())->toBe(true);
    });

    it("is in one state: left out before broken, broken before failing, failing before checking nothing", function() {
        $failing = MatchResult::failed("a", "b", "fail", __FILE__, __LINE__);
        $risky = new ExampleResult("checks nothing", []);
        $risky->markRisky();
        $riskyAndFailing = new ExampleResult("checks nothing and fails", [$failing]);
        $riskyAndFailing->markRisky();

        expect(array_map(fn(ExampleResult $result) => $result->state(), [
            new ExampleResult("passes", [MatchResult::passed()]),
            new ExampleResult("fails", [$failing]),
            new ExampleResult("breaks", [], true),
            new ExampleResult("waits", [], isPending: true),
            new ExampleResult("skips", [], isSkipped: true),
            $risky,
            new ExampleResult("breaks and fails", [$failing], true),
            new ExampleResult("waits and breaks", [], true, isPending: true),
            new ExampleResult("skips and breaks", [$failing], true, isSkipped: true),
            new ExampleResult("waits and skips", [], isPending: true, isSkipped: true),
            $riskyAndFailing,
        ]))->toBe([State::Passed, State::Failed, State::Errored, State::Pending, State::Skipped, State::Risky, State::Errored, State::Pending, State::Skipped, State::Pending, State::Failed]);
    });

    it("is written where it was declared, and nowhere until then", function() {
        $result = new ExampleResult("adds", []);

        expect($result->writtenAt())->toBeNull();

        $result->declaredAt('/project/spec/Calc.spec.php', 12);

        expect((string) $result->writtenAt())->toBe('/project/spec/Calc.spec.php:12');
    });

    it("failed at its first failing expectation, and nowhere when that site did not travel", function() {
        $first = MatchResult::failed("a", "b", "first", __FILE__, $line = __LINE__);
        $second = MatchResult::failed("c", "d", "second", __FILE__, __LINE__);

        expect((string) (new ExampleResult("fails", [MatchResult::passed(), $first, $second]))->failedAt())->toBe(__FILE__ . ':' . $line);
        expect((new ExampleResult("fails", [MatchResult::failed("a", "b", "lost", '', 0)]))->failedAt())->toBeNull();
    });

    it("broke where its error says to look, from the file it is declared in", function() {
        $result = new ExampleResult("breaks", [], true);
        $result->setError(ExampleError::fromReport("boom", "LogicException", "/project/vendor/acme/Client.php", 5, [['file' => '/project/src/App/Gateway.php', 'line' => 8], ['file' => '/project/spec/App/Basket.spec.php', 'line' => 7]]));
        $result->declaredAt('/project/spec/App/Basket.spec.php', 3);

        expect((string) $result->failedAt())->toBe('/project/spec/App/Basket.spec.php:7');
    });

    it("failed nowhere when it passed or was left out", function() {
        $failing = MatchResult::failed("a", "b", "fail", __FILE__, __LINE__);

        expect((new ExampleResult("passes", [MatchResult::passed()]))->failedAt())->toBeNull();
        expect((new ExampleResult("waits", [$failing], isPending: true))->failedAt())->toBeNull();
    });

    it("tells its outcome under the name it is given: what went wrong, why it was left out, where, and how long it took", function() {
        $root = ProjectRoot::at('/project');
        $failing = new ExampleResult("subtracts", [MatchResult::failed(1, 2, "Expected 1 to be 2", '/project/spec/Calc.spec.php', 8)]);
        $failing->declaredAt('/project/spec/Calc.spec.php', 7);
        $failing->setDuration(0.5);
        $risky = new ExampleResult("checks nothing", []);
        $risky->markRisky();
        $outcome = $failing->outcome(['Calc', 'subtracts'], $root);

        expect([$outcome->kind, $outcome->titles, $outcome->state, $outcome->message, $outcome->reason, $outcome->duration])->toBe([Kind::Example, ['Calc', 'subtracts'], State::Failed, "Expected 1 to be 2", null, 0.5]);
        expect([(string) $outcome->failedAt, (string) $outcome->writtenAt])->toBe(['spec/Calc.spec.php:8', 'spec/Calc.spec.php:7']);
        expect([(new ExampleResult("waits", [], isPending: true, reason: "not yet"))->outcome(['waits'], $root)->reason, $risky->outcome(['checks nothing'], $root)->reason])->toBe(["not yet", "no expectation"]);
        expect((new ExampleResult("passes", [MatchResult::passed()]))->outcome(['passes'], $root)->message)->toBeNull();
    });

    it("names in its outcome the class of what it threw, when it errored", function() {
        $root = ProjectRoot::at('/project');
        $broken = new ExampleResult("breaks", [], true);
        $broken->setError(new ExampleError("boom", new \LogicException("boom")));

        expect($broken->outcome(['breaks'], $root)->errorType)->toBe('LogicException');
        expect((new ExampleResult("passes", [MatchResult::passed()]))->outcome(['passes'], $root)->errorType)->toBeNull();
    });

    it("is an error when flagged", function() {
        $result = new ExampleResult("test", [], true);
        expect($result->isError())->toBe(true);
    });

    it("tracks its title", function() {
        $result = new ExampleResult("my test", []);
        expect($result->getTitle())->toBe("my test");
    });

    it("returns its match results", function() {
        $match = MatchResult::passed();
        $result = new ExampleResult("test", [$match]);
        expect($result->getResults())->toHaveCount(1);
    });

    it("stores an error object", function() {
        $result = new ExampleResult("test", [], true);
        $error = new ExampleError("broke", new \RuntimeException("broke"));
        $result->setError($error);
        expect($result->getError())->toBeAnInstanceOf(ExampleError::class);
    });

    it("is pending when flagged", function() {
        $result = new ExampleResult("test", [], false, true);
        expect($result->isPending())->toBe(true);
    });

    it("is not pending by default", function() {
        $result = new ExampleResult("test", []);
        expect($result->isPending())->toBe(false);
    });

    it("knows where its example is declared once told", function() {
        $result = new ExampleResult("test", []);
        expect($result->getFile())->toBeNull();
        expect($result->getLine())->toBeNull();

        $result->declaredAt("/project/spec/App/Basket.spec.php", 7);

        expect($result->getFile())->toBe("/project/spec/App/Basket.spec.php");
        expect($result->getLine())->toBe(7);
    });

    it("tracks duration", function() {
        $result = new ExampleResult("test", []);
        expect($result->getDuration())->toBe(0.0);
        $result->setDuration(0.123);
        expect($result->getDuration())->toBe(0.123);
    });

    it("tracks warnings", function() {
        $result = new ExampleResult("test", []);
        expect($result->hasWarnings())->toBe(false);
        expect($result->getWarnings())->toBe([]);

        $warnings = [['severity' => E_WARNING, 'message' => 'oops', 'file' => __FILE__, 'line' => __LINE__]];
        $result->setWarnings($warnings);
        expect($result->hasWarnings())->toBe(true);
        expect($result->getWarnings())->toHaveCount(1);
    });

    it("is skipped when flagged", function() {
        $result = new ExampleResult("test", [], false, false, true);
        expect($result->isSkipped())->toBe(true);
        expect($result->isPending())->toBe(false);
    });

    it("is not skipped by default", function() {
        $result = new ExampleResult("test", []);
        expect($result->isSkipped())->toBe(false);
    });

    it("carries the reason it was left pending or skipped for", function() {
        $pending = new ExampleResult("test", [], isPending: true, reason: "Needs the rates API");
        $skipped = new ExampleResult("test", [], isSkipped: true, reason: "No network here");

        expect($pending->getReason())->toBe("Needs the rates API");
        expect($skipped->getReason())->toBe("No network here");
    });

    it("knows when it was left out by a focus elsewhere, and is not by default", function() {
        $result = new ExampleResult("test", [], isPending: true);
        expect($result->isLeftOutByFocus())->toBe(false);

        $result->markLeftOutByFocus();
        expect($result->isLeftOutByFocus())->toBe(true);
    });

    it("is risky once marked so, and not by default", function() {
        $result = new ExampleResult("test", []);
        expect($result->isRisky())->toBe(false);

        $result->markRisky();
        expect($result->isRisky())->toBe(true);
    });

    it("has no reason unless given one", function() {
        expect((new ExampleResult("test", [], isPending: true))->getReason())->toBeNull();
    });

    it("tracks deprecations", function() {
        $result = new ExampleResult("test", []);
        expect($result->hasDeprecations())->toBe(false);
        expect($result->getDeprecations())->toBe([]);

        $deprecations = [['severity' => E_DEPRECATED, 'message' => 'old', 'file' => __FILE__, 'line' => __LINE__]];
        $result->setDeprecations($deprecations);
        expect($result->hasDeprecations())->toBe(true);
        expect($result->getDeprecations())->toHaveCount(1);
    });

    it("tracks notices", function() {
        $result = new ExampleResult("test", []);
        expect($result->hasNotices())->toBe(false);
        expect($result->getNotices())->toBe([]);

        $notices = [['severity' => E_NOTICE, 'message' => 'info', 'file' => __FILE__, 'line' => __LINE__]];
        $result->setNotices($notices);
        expect($result->hasNotices())->toBe(true);
        expect($result->getNotices())->toHaveCount(1);
    });

    it("files the notes raised while it ran under their kinds, each once however often it was raised", function() {
        $result = new ExampleResult("test", []);
        $deprecated = ['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => 'a.php', 'line' => 3];

        $result->raised([
            ['severity' => E_WARNING, 'message' => 'oops', 'file' => 'a.php', 'line' => 1],
            $deprecated,
            $deprecated,
            ['severity' => E_USER_NOTICE, 'message' => 'info', 'file' => 'a.php', 'line' => 5],
        ]);

        expect(array_column($result->getWarnings(), 'message'))->toBe(['oops']);
        expect($result->getDeprecations())->toBe([$deprecated]);
        expect(array_column($result->getNotices(), 'message'))->toBe(['info']);
    });

    it("adds what it is told was raised later to what it has, each once", function() {
        $result = new ExampleResult("test", []);
        $first = ['severity' => E_USER_WARNING, 'message' => 'first', 'file' => 'a.php', 'line' => 1];
        $later = ['severity' => E_USER_WARNING, 'message' => 'later', 'file' => 'a.php', 'line' => 9];
        $notice = ['severity' => E_USER_NOTICE, 'message' => 'noted', 'file' => 'a.php', 'line' => 4];

        $result->raised([$first]);
        $result->raised([$later, $first, $notice]);

        expect($result->getWarnings())->toBe([$first, $later]);
        expect($result->getNotices())->toBe([$notice]);
    });

    it("is not a context", function() {
        $result = new ExampleResult("test", []);
        expect($result->isContext())->toBe(false);
    });

    it("returns failure message from first failing match", function() {
        $passing = MatchResult::passed();
        $failing = MatchResult::failed("a", "b", "First failure", __FILE__, __LINE__);
        $failing2 = MatchResult::failed("c", "d", "Second failure", __FILE__, __LINE__);
        $result = new ExampleResult("test", [$passing, $failing, $failing2]);
        expect($result->getFailureMessage())->toBe("First failure");
    });

    it("returns empty string for failure message when no failures", function() {
        $result = new ExampleResult("test", [MatchResult::passed()]);
        expect($result->getFailureMessage())->toBe("");
    });

    it("getMessage returns error message when errored", function() {
        $result = new ExampleResult("test", [], true);
        $error = new ExampleError("error msg", new \RuntimeException("error msg"));
        $result->setError($error);
        expect($result->getMessage())->toBe("error msg");
    });

    it("getMessage returns failure message when not errored", function() {
        $failing = MatchResult::failed("a", "b", "fail msg", __FILE__, __LINE__);
        $result = new ExampleResult("test", [$failing]);
        expect($result->getMessage())->toBe("fail msg");
    });

    it("getMessage returns empty string for passing example", function() {
        $result = new ExampleResult("test", [MatchResult::passed()]);
        expect($result->getMessage())->toBe("");
    });

    it("returns null error by default", function() {
        $result = new ExampleResult("test", []);
        expect($result->getError())->toBeNull();
    });

});
