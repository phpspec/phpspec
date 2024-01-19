<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Wrapper\DelayedCall;
use PhpSpec\Wrapper\Subject\Expectation\DuringCall;

interface Expectation
{
    public function match(string $alias, mixed $subject, array $arguments = array()) : DuringCall|DelayedCall|bool|null;
}
