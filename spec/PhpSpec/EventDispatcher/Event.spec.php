<?php

use PhpSpec\EventDispatcher\Event\ExampleErrored;
use PhpSpec\EventDispatcher\Event\ExampleRunned;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\Specification\ExampleError;

describe("Event classes", function() {

    context("ExampleErrored", function() {
        it("returns its name, title, and error", function() {
            $error = new ExampleError("broke", new \RuntimeException("broke"));
            $event = new ExampleErrored("my test", $error);
            expect($event->getName())->toBe('example.errored');
            expect($event->getTitle())->toBe("my test");
            expect($event->getError())->toBeAnInstanceOf(ExampleError::class);
        });
    });

    context("ExampleRunned", function() {
        it("returns its name and title", function() {
            $event = new ExampleRunned("my test");
            expect($event->getName())->toBe('example.runned');
            expect($event->getTitle())->toBe("my test");
        });
    });

    context("MatchCreated", function() {
        it("returns its name and match closure", function() {
            $match = fn() => true;
            $event = new MatchCreated($match);
            expect($event->getName())->toBe('match.created');
            expect($event->getMatch())->toBe($match);
        });
    });
});
