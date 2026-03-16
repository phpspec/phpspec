<?php

use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Specification\Example;

describe(Example::class, function() {

    it("instantiates", function() {
        $example = new Example("does something", function() {});
        expect($example)->toBeAnInstanceOf(Example::class);
    });

    it("runs the example closure", function() {
        $ran = false;
        $d = DispatcherRegistry::get();
        $example = new Example("does something", function() use (&$ran) {
            $ran = true;
        }, $d);

        $d->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($ran)->toBe(true);
    });

    it("tracks error state", function() {
        $d = DispatcherRegistry::get();
        $example = new Example("breaks", function() {}, $d);

        $d->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );

        expect($example->isError())->toBe(false);
    });

    it("does not run closure when pending", function() {
        $ran = false;
        $d = DispatcherRegistry::get();
        $example = new Example("pending test", function() use (&$ran) {
            $ran = true;
        }, $d);
        $example->setPending(true);

        $d->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($ran)->toBe(false);
        expect($result->isPending())->toBe(true);
    });

    it("returns pending result when closure throws PendingException", function() {
        $d = DispatcherRegistry::get();
        $example = new Example("pending via exception", function() {
            pending("not done yet");
        }, $d);

        $d->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($result->isPending())->toBe(true);
    });

    it("tracks focus state", function() {
        $example = new Example("focused", function() {});
        expect($example->isFocused())->toBe(false);
        $example->setFocused(true);
        expect($example->isFocused())->toBe(true);
    });

    it("tracks duration after run", function() {
        $d = DispatcherRegistry::get();
        $example = new Example("duration test", function() {
            // quick operation
        }, $d);
        $d->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();
        expect($result->getDuration())->toBeOfType('float');
    });

    it("manages matches", function() {
        $example = new Example("matches test", function() {});
        expect($example->getMatches())->toHaveCount(0);
        $example->addMatch(fn() => null);
        expect($example->getMatches())->toHaveCount(1);
        $example->resetMatches();
        expect($example->getMatches())->toHaveCount(0);
    });

    it("tracks doubles", function() {
        $example = new Example("doubles test", function() {});
        $example->addDouble(new \stdClass());
        // No getter for doubles, just ensure it doesn't error
        expect($example)->toBeAnInstanceOf(Example::class);
    });

    it("returns skipped result when closure throws SkippedException", function() {
        $d = DispatcherRegistry::get();
        $example = new Example("skipped via skip()", function() {
            skip("not applicable");
        }, $d);

        $d->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($result->isSkipped())->toBe(true);
        expect($result->isPending())->toBe(false);
        expect($result->isError())->toBe(false);
    });

});
