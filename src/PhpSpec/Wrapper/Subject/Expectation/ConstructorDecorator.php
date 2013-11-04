<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

use Exception;
use PhpSpec\Exception\Example\ErrorException;
use PhpSpec\Util\Instantiator;
use PhpSpec\Wrapper\Subject\WrappedObject;

class ConstructorDecorator extends Decorator implements ExpectationInterface
{
    public function __construct(ExpectationInterface $expectation)
    {
        $this->setExpectation($expectation);
    }

    public function match($alias, $subject, array $arguments = array(), WrappedObject $wrappedObject = null)
    {
        try {
            $wrapped = $subject->getWrappedObject();
        } catch (ErrorException $e) {
            throw $e;
        } catch (Exception $e) {
            if (null !== $wrappedObject && $wrappedObject->getClassName()) {
                $instantiator = new Instantiator();
                $wrapped = $instantiator->instantiate($wrappedObject->getClassName());
            }
        }

        return $this->getExpectation()->match($alias, $wrapped, $arguments);
    }
}
