<?php

namespace spec\PhpSpec\Exception;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\PresenterInterface;

class ExceptionFactorySpec extends ObjectBehavior
{
    private $fixture;
    private $createdException;

    function let(PresenterInterface $presenter)
    {
        $this->beConstructedWith($presenter);
        $this->fixture = new \stdClass;
        $this->fixture->subject   = new \ArrayObject;
        $this->fixture->method    = 'foo';
        $this->fixture->arguments = array('bar');
        $this->fixture->classname = '\ArrayObject';
    }

    function it_creates_a_method_not_found_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->classname}::{$this->fixture->method}")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->classname}::{$this->fixture->method}\"");
        $this->fixture->message = 'Method "\Array::foo" not found';
        $this->createdException = $this->methodNotFound(
            $this->fixture->message,
            $this->fixture->subject,
            $this->fixture->classname,
            $this->fixture->method,
            $this->fixture->arguments
        );

        $this->shouldCreateMethodNotFoundException();
    }

    function it_creates_a_class_not_found_exception()
    {
        $this->fixture->message = 'Class "Foo" not found';
        $this->createdException = $this->classNotFound(
            $this->fixture->message,
            $this->fixture->classname
        );

        $this->shouldCreateClassNotFoundException();
    }

    function shouldCreateMethodNotFoundException()
    {
        $this->createdException->shouldHaveType("PhpSpec\Exception\Fracture\MethodNotFoundException");
        $this->createdException->getMessage()->shouldReturn($this->fixture->message);
        $this->createdException->getSubject()->shouldReturn($this->fixture->subject);
        $this->createdException->getMethodName()->shouldReturn("\"{$this->fixture->classname}::{$this->fixture->method}\"");
        $this->createdException->getArguments()->shouldReturn($this->fixture->arguments);
    }

    function shouldCreateClassNotFoundException()
    {
        $this->createdException->shouldHaveType("PhpSpec\Exception\Fracture\ClassNotFoundException");
        $this->createdException->getMessage()->shouldReturn($this->fixture->message);
        $this->createdException->getClassname()->shouldReturn($this->fixture->classname);
    }
}
