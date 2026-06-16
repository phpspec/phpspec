<?php

use PhpSpec\StoryBDD\StepRegistry;
use PhpSpec\StoryBDD\StepMatch;

describe(StepRegistry::class, function () {

    let("registry", fn() => new StepRegistry());

    it("instantiates", function () {
        expect($this->registry)->toBeAnInstanceOf(StepRegistry::class);
    });

    it("returns null for unmatched step", function () {
        expect($this->registry->match("no such step"))->toBeNull();
    });

    it("matches an exact pattern", function () {
        $called = false;
        $this->registry->addStep("I have a greeting", function () use (&$called) {
            $called = true;
        });
        $match = $this->registry->match("I have a greeting");
        expect($match)->toBeAnInstanceOf(StepMatch::class);
        expect($match->args)->toHaveCount(0);
    });

    it("matches {int} placeholder and captures integer", function () {
        $this->registry->addStep("I have {int} cucumbers", function ($n) {});
        $match = $this->registry->match("I have 5 cucumbers");
        expect($match)->toBeAnInstanceOf(StepMatch::class);
        expect($match->args[0])->toBe("5");
    });

    it("matches {string} placeholder and captures quoted text", function () {
        $this->registry->addStep("I greet {string}", function ($name) {});
        $match = $this->registry->match('I greet "World"');
        expect($match)->toBeAnInstanceOf(StepMatch::class);
        expect($match->args[0])->toBe("World");
    });

    it("matches {word} placeholder", function () {
        $this->registry->addStep("the color is {word}", function ($color) {});
        $match = $this->registry->match("the color is blue");
        expect($match)->toBeAnInstanceOf(StepMatch::class);
        expect($match->args[0])->toBe("blue");
    });

    it("matches {*} wildcard placeholder", function () {
        $this->registry->addStep("I see {*}", function ($text) {});
        $match = $this->registry->match("I see everything is fine");
        expect($match)->toBeAnInstanceOf(StepMatch::class);
        expect($match->args[0])->toBe("everything is fine");
    });

    it("matches multiple placeholders", function () {
        $this->registry->addStep("I have {int} {word} items", function ($n, $kind) {});
        $match = $this->registry->match("I have 3 red items");
        expect($match->args[0])->toBe("3");
        expect($match->args[1])->toBe("red");
    });

    it("does not match partial text", function () {
        $this->registry->addStep("exact", function () {});
        expect($this->registry->match("exact and more"))->toBeNull();
    });

    it("counts registered steps", function () {
        $registry = new StepRegistry();
        expect($registry->count())->toBe(0);
        $registry->addStep("step one", function () {});
        $registry->addStep("step two", function () {});
        expect($registry->count())->toBe(2);
    });

    it("clears all steps", function () {
        $this->registry->addStep("step one", function () {});
        $this->registry->clear();
        expect($this->registry->count())->toBe(0);
    });

    it("stores the original pattern in the match", function () {
        $this->registry->addStep("I have {int} cucumbers", function ($n) {});
        $match = $this->registry->match("I have 5 cucumbers");
        expect($match->pattern)->toBe("I have {int} cucumbers");
    });

});
