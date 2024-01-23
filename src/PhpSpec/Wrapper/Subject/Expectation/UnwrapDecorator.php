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

use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\DelayedCall;

final class UnwrapDecorator extends Decorator implements Expectation
{
    public function __construct(
        Expectation $expectation,
        private Unwrapper $unwrapper
    )
    {
        parent::__construct($expectation);
    }

    public function match(string $alias, mixed $subject, array $arguments = array()) : DuringCall|DelayedCall|bool|null
    {
        $arguments = $this->unwrapper->unwrapAll($arguments);

        return $this->getExpectation()->match($alias, $subject, $arguments);
    }
}
