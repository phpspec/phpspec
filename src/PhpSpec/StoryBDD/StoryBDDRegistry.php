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
 * Lazily initialized on first access so autoloading this file has no side effects.
 */
final class StoryBDDRegistry
{
    private static ?StepRegistry $steps = null;
    private static ?HookRegistry $hooks = null;

    /**
     * Returns the step registry, creating it lazily on first access.
     */
    public static function steps(): StepRegistry
    {
        return self::$steps ??= new StepRegistry();
    }

    /**
     * Returns the hook registry, creating it lazily on first access.
     */
    public static function hooks(): HookRegistry
    {
        return self::$hooks ??= new HookRegistry();
    }

    /**
     * Resets to fresh registries. Used between in-process runs.
     */
    public static function init(): void
    {
        self::$steps = new StepRegistry();
        self::$hooks = new HookRegistry();
    }

    /**
     * Captures the current registries for later restoration.
     *
     * @return array{steps: StepRegistry, hooks: HookRegistry}
     */
    public static function saveState(): array
    {
        return ['steps' => self::steps(), 'hooks' => self::hooks()];
    }

    /**
     * Restores previously saved registries.
     *
     * @param array{steps: StepRegistry, hooks: HookRegistry} $state
     */
    public static function restoreState(array $state): void
    {
        self::$steps = $state['steps'];
        self::$hooks = $state['hooks'];
    }
}
