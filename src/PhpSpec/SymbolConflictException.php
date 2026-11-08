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

namespace PhpSpec;

/**
 * @internal
 * Thrown when an in-process run would see a class or function an earlier
 * run declared other than as its project's files declare it, so the run
 * goes to a fresh process instead.
 */
final class SymbolConflictException extends \RuntimeException {}
