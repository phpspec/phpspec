<?php

use PhpSpec\StoryBDD\HookRegistry;
use PhpSpec\StoryBDD\StepWorld;

describe(HookRegistry::class, function () {

    it("runs beforeFeature hooks", function () {
        $hooks = new HookRegistry();
        $called = false;
        $hooks->addBeforeFeature(function () use (&$called) {
            $called = true;
        });
        $hooks->runBeforeFeature();
        expect($called)->toBeTrue();
    });

    it("runs beforeScenario hooks bound to world", function () {
        $hooks = new HookRegistry();
        $hooks->addBeforeScenario(function () {
            $this->setup = true;
        });
        $world = new StepWorld();
        $hooks->runBeforeScenario($world);
        expect($world->setup)->toBeTrue();
    });

    it("runs beforeStep hooks bound to world", function () {
        $hooks = new HookRegistry();
        $hooks->addBeforeStep(function () {
            $this->stepPrepped = true;
        });
        $world = new StepWorld();
        $hooks->runBeforeStep($world);
        expect($world->stepPrepped)->toBeTrue();
    });

    it("runs afterFeature hooks", function () {
        $hooks = new HookRegistry();
        $called = false;
        $hooks->addAfterFeature(function () use (&$called) {
            $called = true;
        });
        $hooks->runAfterFeature();
        expect($called)->toBeTrue();
    });

    it("runs afterScenario hooks bound to world", function () {
        $hooks = new HookRegistry();
        $hooks->addAfterScenario(function () {
            $this->tornDown = true;
        });
        $world = new StepWorld();
        $hooks->runAfterScenario($world);
        expect($world->tornDown)->toBeTrue();
    });

    it("runs afterStep hooks bound to world", function () {
        $hooks = new HookRegistry();
        $hooks->addAfterStep(function () {
            $this->stepDone = true;
        });
        $world = new StepWorld();
        $hooks->runAfterStep($world);
        expect($world->stepDone)->toBeTrue();
    });

    it("clears all hooks", function () {
        $hooks = new HookRegistry();
        $calls = [];
        $hooks->addBeforeFeature(function () use (&$calls) { $calls[] = 'beforeFeature'; });
        $hooks->addBeforeScenario(function () use (&$calls) { $calls[] = 'beforeScenario'; });
        $hooks->addBeforeStep(function () use (&$calls) { $calls[] = 'beforeStep'; });
        $hooks->addAfterFeature(function () use (&$calls) { $calls[] = 'afterFeature'; });
        $hooks->addAfterScenario(function () use (&$calls) { $calls[] = 'afterScenario'; });
        $hooks->addAfterStep(function () use (&$calls) { $calls[] = 'afterStep'; });
        $hooks->clear();
        $world = new StepWorld();
        $hooks->runBeforeFeature();
        $hooks->runBeforeScenario($world);
        $hooks->runBeforeStep($world);
        $hooks->runAfterFeature();
        $hooks->runAfterScenario($world);
        $hooks->runAfterStep($world);
        expect($calls)->toBe([]);
    });

    it("runs multiple hooks in order", function () {
        $hooks = new HookRegistry();
        $order = [];
        $hooks->addBeforeFeature(function () use (&$order) { $order[] = 'first'; });
        $hooks->addBeforeFeature(function () use (&$order) { $order[] = 'second'; });
        $hooks->runBeforeFeature();
        expect($order)->toBe(['first', 'second']);
    });

});
