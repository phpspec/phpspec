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

    it("clears all hooks", function () {
        $hooks = new HookRegistry();
        $featureCalled = false;
        $scenarioCalled = false;
        $stepCalled = false;
        $hooks->addBeforeFeature(function () use (&$featureCalled) { $featureCalled = true; });
        $hooks->addBeforeScenario(function () use (&$scenarioCalled) { $scenarioCalled = true; });
        $hooks->addBeforeStep(function () use (&$stepCalled) { $stepCalled = true; });
        $hooks->clear();
        $hooks->runBeforeFeature();
        $world = new StepWorld();
        $hooks->runBeforeScenario($world);
        $hooks->runBeforeStep($world);
        expect($featureCalled)->toBeFalse();
        expect($scenarioCalled)->toBeFalse();
        expect($stepCalled)->toBeFalse();
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
