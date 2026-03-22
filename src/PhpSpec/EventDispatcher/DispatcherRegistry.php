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

namespace PhpSpec\EventDispatcher;

/**
 * @internal Not part of the public API.
 *
 * Static service locator holding the singleton Dispatcher instance.
 *
 * DSL functions and framework internals use this to reach the Dispatcher
 * without requiring constructor injection. All global state is confined
 * to this single class.
 */
final class DispatcherRegistry
{
    private static ?Dispatcher $instance = null;

    /**
     * Returns the current Dispatcher, creating one lazily if needed.
     */
    public static function dispatcher(): Dispatcher
    {
        return self::$instance ??= new Dispatcher();
    }

    /**
     * Replaces the current Dispatcher instance.
     *
     * Used by InProcessRunner to swap in a fresh dispatcher for nested runs.
     */
    public static function set(Dispatcher $dispatcher): void
    {
        self::$instance = $dispatcher;
    }

    /**
     * Resets to a fresh Dispatcher instance.
     */
    public static function reset(): void
    {
        self::$instance = new Dispatcher();
    }
}
