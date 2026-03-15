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

namespace PhpSpec\StoryBDD;

/**
 * Stores and executes lifecycle hooks for feature, scenario, and step events.
 * Scenario and step hooks are bound to the StepWorld instance as $this.
 */
final class HookRegistry
{
    /** @var \Closure[] hooks executed before each feature */
    private array $beforeFeature = [];
    /** @var \Closure[] hooks executed before each scenario, bound to StepWorld */
    private array $beforeScenario = [];
    /** @var \Closure[] hooks executed before each step, bound to StepWorld */
    private array $beforeStep = [];

    /**
     * Registers a hook to run before each feature.
     *
     * @param \Closure $hook closure invoked without binding
     * @return void
     */
    public function addBeforeFeature(\Closure $hook): void
    {
        $this->beforeFeature[] = $hook;
    }

    /**
     * Registers a hook to run before each scenario, bound to the StepWorld.
     *
     * @param \Closure $hook closure called with StepWorld as $this
     * @return void
     */
    public function addBeforeScenario(\Closure $hook): void
    {
        $this->beforeScenario[] = $hook;
    }

    /**
     * Registers a hook to run before each step, bound to the StepWorld.
     *
     * @param \Closure $hook closure called with StepWorld as $this
     * @return void
     */
    public function addBeforeStep(\Closure $hook): void
    {
        $this->beforeStep[] = $hook;
    }

    /**
     * Executes all registered before-feature hooks in registration order.
     *
     * @return void
     */
    public function runBeforeFeature(): void
    {
        foreach ($this->beforeFeature as $hook) {
            $hook();
        }
    }

    /**
     * Executes all registered before-scenario hooks, binding each to the world object.
     *
     * @param object $world the StepWorld instance to bind as $this
     * @return void
     */
    public function runBeforeScenario(object $world): void
    {
        foreach ($this->beforeScenario as $hook) {
            $hook->call($world);
        }
    }

    /**
     * Executes all registered before-step hooks, binding each to the world object.
     *
     * @param object $world the StepWorld instance to bind as $this
     * @return void
     */
    public function runBeforeStep(object $world): void
    {
        foreach ($this->beforeStep as $hook) {
            $hook->call($world);
        }
    }

    /**
     * Removes all registered hooks from every lifecycle phase.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->beforeFeature = [];
        $this->beforeScenario = [];
        $this->beforeStep = [];
    }
}
