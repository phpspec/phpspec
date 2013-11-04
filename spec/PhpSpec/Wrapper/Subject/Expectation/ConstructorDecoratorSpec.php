<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Subject\WrappedObject;
use Prophecy\Argument;

use PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface;

class ConstructorDecoratorSpec extends ObjectBehavior
{
    function let(ExpectationInterface $expectation)
    {
        $this->beConstructedWith($expectation);
    }

    function it_rethrows_php_errors_as_phpspec_error_exceptions(Subject $subject, WrappedObject $wrapped)
    {
        // calling $subject->getWrappedObject() actually breaks Collaborator
        // as there is a method with that name on it.
        // One of the odds of testing a framework with itself
        $subject->__call('getWrappedObject', array())->willThrow('PhpSpec\Exception\Example\ErrorException');
        $this->shouldThrow('PhpSpec\Exception\Example\ErrorException')->duringMatch('be', $subject, array(), $wrapped);
    }

    function it_ignores_any_other_exception(Subject $subject, WrappedObject $wrapped)
    {
        $subject->__call('getWrappedObject', array())->willThrow('\Exception');
        $wrapped->getClassName()->willReturn('\ArrayObject');
        $this->shouldNotThrow('\Exception')->duringMatch('be', $subject, array(), $wrapped);
    }

}
