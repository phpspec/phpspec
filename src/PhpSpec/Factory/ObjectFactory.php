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

declare(strict_types=1);

namespace PhpSpec\Factory;

use PhpSpec\Exception\Fracture\FactoryDoesNotReturnObjectException;

class ObjectFactory
{
    /**
     * @throws FactoryDoesNotReturnObjectException when callable returns non object
     */
    public function instantiateFromCallable(
        callable $callable,
        array $arguments = []
    ): object {
        $instance = \call_user_func_array($callable, $arguments);

        if (!\is_object($instance)) {
            throw new FactoryDoesNotReturnObjectException(sprintf(
                'The %s did not return an object, returned %s instead',
                $this->callableToString($callable),
                \gettype($instance)
            ));
        }

        return $instance;
    }

    private function callableToString(callable $callable): string
    {
        if (\is_array($callable)) {
            return 'method ' . $callable[0] . '::' . $callable[1];
        }
        return 'function ' . $callable;
    }
}