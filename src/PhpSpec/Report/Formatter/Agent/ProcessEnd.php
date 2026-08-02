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

namespace PhpSpec\Report\Formatter\Agent;

use Closure;

/**
 * @internal
 * The last word before the process goes: a way to be called back as PHP shuts
 * down, told what killed it when something did. The agent document promises to
 * be there whatever happens, and a compile error is not something a catch block
 * can keep that promise through.
 */
interface ProcessEnd
{
    /**
     * Registers the callback to run as the process ends. It receives the fatal
     * error that ended it, or null when the process simply finished.
     *
     * @param Closure(Fatal|null): void $ending
     */
    public function atEnd(Closure $ending): void;
}
