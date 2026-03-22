<?php

use PhpSpec\Specification\Example;

describe(Example::class, function() {

    it("instantiates", function() {
        $example = new Example("does something", function() {});
        expect($example)->toBeAnInstanceOf(Example::class);
    });

    it("runs the example closure", function() {
        $ran = false;
        $example = new Example("does something", function() use (&$ran) {
            $ran = true;
        });

        \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher()->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($ran)->toBe(true);
    });

    it("tracks error state", function() {
        $example = new Example("breaks", function() {});

        \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher()->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );

        expect($example->isError())->toBe(false);
    });

    it("does not run closure when pending", function() {
        $ran = false;
        $example = new Example("pending test", function() use (&$ran) {
            $ran = true;
        });
        $example->setPending(true);

        \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher()->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($ran)->toBe(false);
        expect($result->isPending())->toBe(true);
    });

    it("returns pending result when closure throws PendingException", function() {
        $example = new Example("pending via exception", function() {
            pending("not done yet");
        });

        \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher()->addSubscriber(
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
        $example = new Example("duration test", function() {
            // quick operation
        });
        \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher()->addSubscriber(
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
        $example = new Example("skipped via skip()", function() {
            skip("not applicable");
        });

        \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher()->addSubscriber(
            new \PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber($example)
        );
        $result = $example->run();

        expect($result->isSkipped())->toBe(true);
        expect($result->isPending())->toBe(false);
        expect($result->isError())->toBe(false);
    });

    it("catches errors in closure and marks as error", function() {
        // Use a saved dispatcher and a fresh one to isolate
        $saved = \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher();
        \PhpSpec\EventDispatcher\DispatcherRegistry::reset();

        $example = new Example("throws", function() {
            throw new \RuntimeException("boom");
        });

        $result = $example->run();
        expect($result->isError())->toBe(true);

        \PhpSpec\EventDispatcher\DispatcherRegistry::set($saved);
    });

    it("addExampleResult sets error flag for error results", function() {
        $example = new Example("error result", function() {});
        $errorResult = new \PhpSpec\Result\ExampleResult("test", [], true);
        $example->addExampleResult($errorResult);
        expect($example->isError())->toBe(true);
    });

    it("addExampleResult does not set error flag for passing results", function() {
        $example = new Example("pass result", function() {});
        $passResult = new \PhpSpec\Result\ExampleResult("test", []);
        $example->addExampleResult($passResult);
        expect($example->isError())->toBe(false);
    });

    it("captures and classifies warnings during example run", function() {
        $example = new Example("triggers warning", function() {
            trigger_error("test warning", E_USER_WARNING);
        });
        $result = $example->run();
        expect($result->hasWarnings())->toBe(true);
        expect($result->getWarnings()[0]['message'])->toBe("test warning");
    });

    it("captures and classifies deprecations during example run", function() {
        $example = new Example("triggers deprecation", function() {
            trigger_error("test deprecation", E_USER_DEPRECATED);
        });
        $result = $example->run();
        expect($result->hasDeprecations())->toBe(true);
        expect($result->getDeprecations()[0]['message'])->toBe("test deprecation");
    });

    it("captures and classifies notices during example run", function() {
        $example = new Example("triggers notice", function() {
            trigger_error("test notice", E_USER_NOTICE);
        });
        $result = $example->run();
        expect($result->hasNotices())->toBe(true);
        expect($result->getNotices()[0]['message'])->toBe("test notice");
    });

    it("measures execution duration", function() {
        $example = new Example("timed", function() {
            usleep(1000); // 1ms
        });
        $result = $example->run();
        expect($result->getDuration())->toBeGreaterThan(0);
    });

    it("resolves type-hinted closure arguments as mocks", function() {
        $receivedService = null;
        $example = new Example("injection test", function(\JsonSerializable $service) use (&$receivedService) {
            $receivedService = $service;
        });
        $result = $example->run();
        expect($receivedService)->toBeAnInstanceOf(\JsonSerializable::class);
    });

});
