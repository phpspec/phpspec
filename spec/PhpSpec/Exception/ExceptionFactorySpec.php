<?php

namespace spec\PhpSpec\Exception;

use PhpSpec\Exception\Wrapper\SubjectException;

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
        $this->fixture->property = 'zoo';
    }

    function it_creates_a_method_not_found_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->classname}::{$this->fixture->method}")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->classname}::{$this->fixture->method}\"");
        $this->fixture->message = 'Method "\ArrayObject::foo" not found.';
        $this->createdException = $this->methodNotFound(
            $this->fixture->subject,
            $this->fixture->classname,
            $this->fixture->method,
            $this->fixture->arguments
        );

        $this->shouldCreateMethodNotFoundException();
    }

    function it_creates_a_class_not_found_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->classname}")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->classname}\"");
        $this->fixture->message = 'Class "Foo" not found';
        $this->createdException = $this->classNotFound(
            $this->fixture->message,
            $this->fixture->classname
        );

        $this->shouldCreateClassNotFoundException();
    }

    function it_creates_a_property_not_found_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->property}")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->property}\"");
        $this->fixture->message = 'Property "zoo" not found';
        $this->createdException = $this->propertyNotFound(
            $this->fixture->message,
            $this->fixture->subject,
            $this->fixture->property
        );

        $this->shouldCreatePropertyNotFoundException();
    }

    function it_creates_a_calling_method_on_non_object_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->method}()")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->method}()\"");
        $fixtureMessage = "Call to a member function \"{$this->fixture->method}()\" on a non-object.";
        $exception = $this->callingMethodOnNonObject($this->fixture->method);
        $exception->shouldHaveType('PhpSpec\Exception\Wrapper\SubjectException');
        $exception->getMessage()->shouldBe($fixtureMessage);
    }

    function it_creates_a_setting_property_on_non_object_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->property}")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->property}\"");
        $fixtureMessage = "Setting property \"{$this->fixture->property}\" on a non-object.";
        $exception = $this->settingPropertyOnNonObject($this->fixture->property);
        $exception->shouldHaveType('PhpSpec\Exception\Wrapper\SubjectException');
        $exception->getMessage()->shouldBe($fixtureMessage);
    }

    function it_creates_an_accessing_property_on_non_object_exception(PresenterInterface $presenter)
    {
        $presenter->presentString("{$this->fixture->property}")
            ->shouldBeCalled()
            ->willReturn("\"{$this->fixture->property}\"");
        $fixtureMessage = "Getting property \"{$this->fixture->property}\" on a non-object.";
        $exception = $this->gettingPropertyOnNonObject($this->fixture->property);
        $exception->shouldHaveType('PhpSpec\Exception\Wrapper\SubjectException');
        $exception->getMessage()->shouldBe($fixtureMessage);
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
        $this->createdException->getClassname()->shouldReturn("\"{$this->fixture->classname}\"");
    }

    function shouldCreatePropertyNotFoundException()
    {
        $this->createdException->shouldHaveType("PhpSpec\Exception\Fracture\PropertyNotFoundException");
        $this->createdException->getMessage()->shouldReturn($this->fixture->message);
        $this->createdException->getSubject()->shouldReturn($this->fixture->subject);
        $this->createdException->getProperty()->shouldReturn("\"{$this->fixture->property}\"");
    }
}
