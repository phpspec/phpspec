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
 * @internal Not part of the public API.
 *
 * Static registry holding the global StepRegistry and HookRegistry instances.
 * Initialized on file load and used by the DSL functions (given/when/then/etc.).
 */
final class StoryBDDRegistry
{
    /** @var StepRegistry the global step definition registry */
    public static StepRegistry $steps;
    /** @var HookRegistry the global hook registry */
    public static HookRegistry $hooks;

    /**
     * Initializes fresh StepRegistry and HookRegistry instances.
     */
    public static function init(): void
    {
        self::$steps = new StepRegistry();
        self::$hooks = new HookRegistry();
    }

    /**
     * Clears all registered steps and hooks from both registries.
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
     */
    public static function restoreState(array $state): void
    {
        self::$steps = $state['steps'];
        self::$hooks = $state['hooks'];
    }
}
