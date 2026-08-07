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

namespace PhpSpec\Guard;

use RuntimeException;

/**
 * @internal
 * Raised by whatever discovers that this checkout cannot be judged: a baseline
 * that will not parse, a commit git cannot answer for.
 *
 * It travels as an exception because the discovery happens deep in a reader
 * whose answer is a value with no room for "I don't know", and because every
 * such discovery has to end up in the same place: one sentence for the reader,
 * naming what to do next.
 */
final class CannotJudge extends RuntimeException {}
