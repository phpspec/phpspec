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
 * Static service locator holding the singleton Dispatcher instance.
 *
 * DSL functions and inline-created objects use this to reach the Dispatcher
 * without requiring constructor injection.
 */
final class DispatcherRegistry
{
    private static ?Dispatcher $instance = null;

    public static function set(Dispatcher $dispatcher): void
    {
        self::$instance = $dispatcher;
    }

    public static function get(): Dispatcher
    {
        if (self::$instance === null) {
            self::$instance = new Dispatcher();
        }

        return self::$instance;
    }
}
