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

namespace PhpSpec\Console;

/**
 * A command with options that only phpspec itself passes, kept out of its
 * help.
 */
interface InternalOptions
{
    /**
     * @return list<string> the names of the options the help leaves out
     */
    public function internalOptions(): array;
}
