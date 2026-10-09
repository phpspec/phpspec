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

use RuntimeException;

/**
 * What a project states in its configuration cannot be taken as it is: a
 * file that does not parse, a key nothing reads, a value of the wrong kind.
 * The message names the file and what to change.
 */
final class ConfigurationException extends RuntimeException {}
