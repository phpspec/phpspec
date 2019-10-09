<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use Error;
use Exception;
use PhpSpec\Exception\ErrorException;
use PhpSpec\Exception\Fracture\FractureException;
use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Subject\Expectation\Expectation;
use PhpSpec\Wrapper\Subject\WrappedObject;
use Prophecy\Argument;
use stdClass;

class ConstructorDecoratorSpec extends ObjectBehavior
{
    function let(Expectation $expectation)
    {
        $this->beConstructedWith($expectation);
    }

    function it_rethrows_php_errors_as_phpspec_error_exceptions(Subject $subject, WrappedObject $wrapped)
    {
        $subject->__call('getWrappedObject', [])->willThrow(new Error());
        $this->shouldThrow(ErrorException::class)->duringMatch('be', $subject, array(), $wrapped);
    }

    function it_rethrows_fracture_errors(Subject $subject, WrappedObject $wrapped)
    {
        $subject->__call('getWrappedObject', [])->willThrow(FractureException::class);
        $this->shouldThrow(FractureException::class)->duringMatch('be', $subject, array(), $wrapped);
    }

    function it_throws_phpspec_error_exception_when_wrapped_object_not_provided(Subject $subject)
    {
        $exception = new Exception();
        $subject->__call('getWrappedObject', array())->willThrow($exception);
        $this->shouldThrow($exception)->duringMatch('be', $subject);
    }

    function it_returns_match_from_expectation_when_subject_throws_error(Expectation $expectation, Subject $subject, WrappedObject $wrapped)
    {
        $alias = 'alias';
        $match = new \stdClass();
        $arguments = ['arg1'];

        $subject->__call('getWrappedObject', [])->willThrow(new Error());
        $wrapped->getClassName()->willReturn(\stdClass::class);
        $expectation->match($alias, Argument::type('object'), $arguments)->shouldBeCalledTimes(1)->willReturn($match);

        $this->beConstructedWith($expectation);

        $this->match($alias, $subject, $arguments, $wrapped)->shouldReturn($match);
    }
}
