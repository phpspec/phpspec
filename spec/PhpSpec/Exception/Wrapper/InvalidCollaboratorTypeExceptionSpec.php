<?php

namespace spec\PhpSpec\Exception\Wrapper;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InvalidCollaboratorTypeExceptionSpec extends ObjectBehavior
{
    function let(\ReflectionParameter $parameter, \ReflectionFunctionAbstract $function)
    {
        $this->beConstructedWith($parameter, $function);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Exception\Wrapper\InvalidCollaboratorTypeException');
        $this->shouldHaveType('PhpSpec\Exception\Wrapper\CollaboratorException');
    }

    function it_generates_correct_message_based_on_function_and_parameter(
        \ReflectionParameter $parameter,
        \ReflectionMethod $function,
        \ReflectionClass $class
    ) {
        $parameter->getPosition()->willReturn(2);
        $function->getDeclaringClass()->willReturn($class);
        $class->getName()->willReturn('Acme\Foo');
        $function->getName()->willReturn('bar');

        $this->getMessage()->shouldStartWith('Collaborator must be an object: argument 2 defined in Acme\Foo::bar.');
    }

    function it_sets_cause(\ReflectionFunction $function)
    {
        $this->getCause()->shouldReturn($function);
    }

}
