<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PhpSpec\StoryBDD\HookRegistry;
use PhpSpec\StoryBDD\StepRegistry;

/**
 * Registers a Given step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional {string}/{int}/{word}/{*} placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function given(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::$steps->addStep($pattern, $fn);
}

/**
 * Registers a When step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function when(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::$steps->addStep($pattern, $fn);
}

/**
 * Registers a Then step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function then(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::$steps->addStep($pattern, $fn);
}

/**
 * Registers an And step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function step_and(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::$steps->addStep($pattern, $fn);
}

/**
 * Registers a But step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function step_but(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::$steps->addStep($pattern, $fn);
}

/**
 * Registers a hook to run before each feature.
 *
 * @param Closure $fn closure invoked before each feature executes
 * @return void
 */
function beforeFeature(Closure $fn): void
{
    StoryBDDRegistry::$hooks->addBeforeFeature($fn);
}

/**
 * Registers a hook to run before each scenario, bound to the StepWorld.
 *
 * @param Closure $fn closure invoked with StepWorld as $this before each scenario
 * @return void
 */
function beforeScenario(Closure $fn): void
{
    StoryBDDRegistry::$hooks->addBeforeScenario($fn);
}

/**
 * Registers a hook to run before each step, bound to the StepWorld.
 *
 * @param Closure $fn closure invoked with StepWorld as $this before each step
 * @return void
 */
function beforeStep(Closure $fn): void
{
    StoryBDDRegistry::$hooks->addBeforeStep($fn);
}

/**
 * @internal
 * Static registry holding the global StepRegistry and HookRegistry instances.
 * Initialized on file load and used by the DSL functions (given/when/then/etc.).
 */
class StoryBDDRegistry
{
    /** @var StepRegistry the global step definition registry */
    public static StepRegistry $steps;
    /** @var HookRegistry the global hook registry */
    public static HookRegistry $hooks;

    /**
     * Initializes fresh StepRegistry and HookRegistry instances.
     *
     * @return void
     */
    public static function init(): void
    {
        self::$steps = new StepRegistry();
        self::$hooks = new HookRegistry();
    }

    /**
     * Clears all registered steps and hooks from both registries.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$steps->clear();
        self::$hooks->clear();
    }

    /**
     * Captures the current StepRegistry and HookRegistry instances for later restoration.
     *
     * @return array{steps: StepRegistry, hooks: HookRegistry}
     */
    public static function saveState(): array
    {
        return ['steps' => self::$steps, 'hooks' => self::$hooks];
    }

    /**
     * Restores previously saved StepRegistry and HookRegistry instances.
     *
     * @param array{steps: StepRegistry, hooks: HookRegistry} $state
     * @return void
     */
    public static function restoreState(array $state): void
    {
        self::$steps = $state['steps'];
        self::$hooks = $state['hooks'];
    }
}

StoryBDDRegistry::init();
