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

use PhpSpec\Exception\ErrorException;
use PhpSpec\Exception\Fracture\FractureException;
use PhpSpec\Util\Instantiator;
use PhpSpec\Wrapper\Subject\WrappedObject;

final class ConstructorDecorator extends Decorator implements Expectation
{
    public function __construct(Expectation $expectation)
    {
        $this->setExpectation($expectation);
    }

    /**
     * @throws ErrorException
     * @throws FractureException
     */
    public function match(string $alias, mixed $subject, array $arguments = [], WrappedObject $wrappedObject = null): mixed
    {
        try {
            $wrapped = $subject->getWrappedObject();
        } catch (ErrorException|FractureException $e) {
            throw $e;
        } catch (\Exception $e) {
            $className = $wrappedObject?->getClassName();
            if ($wrappedObject === null || $className === null) {
                throw $e;
            }

            $instantiator = new Instantiator();
            $wrapped = $instantiator->instantiate($className);
        }

        return $this->getExpectation()->match($alias, $wrapped, $arguments);
    }
}
