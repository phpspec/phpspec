<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

use Exception;
use PhpSpec\Exception\Example\ErrorException;
use PhpSpec\Util\Instantiator;
use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Unwrapper;

class ConstructorDecorator extends Decorator implements ExpectationInterface
{
    private $unwrapper;

    public function __construct(ExpectationInterface $expectation, Unwrapper $unwrapper)
    {
        $this->setExpectation($expectation);
        $this->unwrapper = $unwrapper;
    }

    public function match($alias, $subject, array $arguments = array(), WrappedObject $wrappedObject = null)
    {
        if ($alias === 'throw') {
            $subject->beConstructedWith($arguments);
        }

        try {
            $wrapped = $subject->getWrappedObject();
        } catch (ErrorException $e) {
            throw $e;
        } catch (Exception $e) {
            if ($wrappedObject->getClassName()) {
                $instantiator = new Instantiator();
                $wrapped = $instantiator->instantiate($wrappedObject->getClassName());
            }
        }

        return $this->getExpectation()->match($alias, $wrapped, $arguments);
    }
}
