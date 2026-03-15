<?php

use PhpSpec\StoryBDD\StepRegistry;
use PhpSpec\StoryBDD\HookRegistry;

describe('StoryBDD DSL functions', function () {

    beforeEach(function () {
        \StoryBDDRegistry::init();
    });

    it("registers a step via given()", function () {
        given("a given step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
    });

    it("registers a step via when()", function () {
        when("a when step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
    });

    it("registers a step via then()", function () {
        then("a then step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
    });

    it("registers a step via step_and()", function () {
        step_and("an and step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
    });

    it("registers a step via step_but()", function () {
        step_but("a but step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
    });

    it("registers beforeFeature hook", function () {
        $called = false;
        beforeFeature(function () use (&$called) {
            $called = true;
        });
        \StoryBDDRegistry::$hooks->runBeforeFeature();
        expect($called)->toBeTrue();
    });

    it("registers beforeScenario hook", function () {
        $called = false;
        beforeScenario(function () use (&$called) {
            $called = true;
        });
        $world = new \PhpSpec\StoryBDD\StepWorld();
        \StoryBDDRegistry::$hooks->runBeforeScenario($world);
        expect($called)->toBeTrue();
    });

    it("registers beforeStep hook", function () {
        $called = false;
        beforeStep(function () use (&$called) {
            $called = true;
        });
        $world = new \PhpSpec\StoryBDD\StepWorld();
        \StoryBDDRegistry::$hooks->runBeforeStep($world);
        expect($called)->toBeTrue();
    });

    it("reinitializes with init()", function () {
        given("a step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
        \StoryBDDRegistry::init();
        expect(\StoryBDDRegistry::$steps->count())->toBe(0);
    });

    it("resets the registry", function () {
        given("a step", function () {});
        expect(\StoryBDDRegistry::$steps->count())->toBe(1);
        \StoryBDDRegistry::reset();
        expect(\StoryBDDRegistry::$steps->count())->toBe(0);
    });

});
