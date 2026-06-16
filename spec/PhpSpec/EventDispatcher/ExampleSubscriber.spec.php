<?php

use PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber;
use PhpSpec\EventDispatcher\Event\ExampleRunned;
use PhpSpec\EventDispatcher\Event\ExampleErrored;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\Result\MatchResult;
use PhpSpec\Specification\Example;
use PhpSpec\Specification\ExampleError;

describe(ExampleSubscriber::class, function() {

    it("returns subscribed events", function() {
        $example = new Example("test", function() {});
        $subscriber = new ExampleSubscriber($example);
        $events = $subscriber->getSubscribedEvents();
        expect($events)->toHaveKey(ExampleRunned::NAME);
        expect($events)->toHaveKey(MatchCreated::NAME);
        expect($events)->toHaveKey(ExampleErrored::NAME);
    });

    it("collects matches and produces result on example runned", function() {
        $example = new Example("test", function() {});
        $subscriber = new ExampleSubscriber($example);

        $match = fn() => MatchResult::passed();
        $subscriber->onMatchCreated(new MatchCreated($match));
        $subscriber->onExampleRunned(new ExampleRunned("test"));

        // After onExampleRunned, example should have received a result
        // We verify indirectly: the matches were consumed (resetMatches called)
        expect($example->getMatches())->toHaveCount(0);
    });

    it("resets matches when example has error", function() {
        $example = new Example("test", function() {});

        // Simulate error state: dispatch error event first
        $subscriber = new ExampleSubscriber($example);

        $error = new ExampleError("broke", new \RuntimeException("broke"));
        $subscriber->onExampleErrored(new ExampleErrored("test", $error));

        // Now add a match after the error
        $match = fn() => MatchResult::passed();
        $subscriber->onMatchCreated(new MatchCreated($match));

        // On runned with error state, matches should be reset without running
        $subscriber->onExampleRunned(new ExampleRunned("test"));
        expect($example->getMatches())->toHaveCount(0);
    });

    it("creates error result on onExampleErrored", function() {
        $example = new Example("errored test", function() {});
        $subscriber = new ExampleSubscriber($example);

        $error = new ExampleError("something broke", new \RuntimeException("something broke"));
        $subscriber->onExampleErrored(new ExampleErrored("errored test", $error));

        expect($example->isError())->toBe(true);
    });

});
