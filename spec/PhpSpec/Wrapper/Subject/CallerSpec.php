<?php

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\ExceptionFactory;
use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Wrapper;
use PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Formatter\Presenter\PresenterInterface;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CallerSpec extends ObjectBehavior
{
    function let(WrappedObject $wrappedObject, ExampleNode $example,
        EventDispatcherInterface $dispatcher, PresenterInterface $presenter,
        ExceptionFactory $exceptions, MatcherManager $matchers, Wrapper $wrapper)
    {
        $this->beConstructedWith($wrappedObject, $example, $dispatcher,
            $presenter, $exceptions, $matchers, $wrapper);
    }

    function it_dispatches_method_call_events(EventDispatcherInterface $dispatcher, WrappedObject $wrappedObject)
    {
        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn(new \ArrayObject());

        $dispatcher->dispatch(
            'beforeMethodCall',
            Argument::type('PhpSpec\Event\MethodCallEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterMethodCall',
            Argument::type('PhpSpec\Event\MethodCallEvent')
        )->shouldBeCalled();

        $this->call('count');
    }

    function it_sets_a_property_on_the_wrapped_object(WrappedObject $wrappedObject)
    {
        $obj = new \stdClass;
        $obj->id = 1;

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);

        $this->set('id', 2)->shouldReturn(2);
    }

    function it_proxies_method_calls_to_wrapped_object(\ArrayObject $obj, WrappedObject $wrappedObject)
    {
        $obj->asort()->shouldBeCalled();
        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);
        $this->call('asort');
    }

    function it_delegates_throwing_class_not_found_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $wrappedObject->isInstantiated()->willReturn(false);
        $wrappedObject->getClassName()->willReturn('Foo');

        $exceptions->classNotFound('Foo')
            ->willReturn(new \PhpSpec\Exception\Fracture\ClassNotFoundException(
                'Class "Foo" does not exist.',
                '"Foo"'
            ))
            ->shouldBeCalled();
        $this->shouldThrow('\PhpSpec\Exception\Fracture\ClassNotFoundException')
            ->duringGetWrappedObject();
    }

    function it_delegates_throwing_method_not_found_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $obj = new \ArrayObject;

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);
        $wrappedObject->getClassName()->willReturn('ArrayObject');

        $exceptions->methodNotFound('ArrayObject', 'foo', array())
            ->willReturn(new \PhpSpec\Exception\Fracture\MethodNotFoundException(
                'Method "foo" not found.',
                $obj,
                '"ArrayObject::foo"',
                array()
            ))
            ->shouldBeCalled();
        $this->shouldThrow('\PhpSpec\Exception\Fracture\MethodNotFoundException')
            ->duringCall('foo');
    }

    function it_delegates_throwing_property_not_found_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $obj = new \ArrayObject;

        $wrappedObject->isInstantiated()->willReturn(true);
        $wrappedObject->getInstance()->willReturn($obj);

        $exceptions->propertyNotFound($obj, 'nonExistentProperty')
            ->willReturn(new \PhpSpec\Exception\Fracture\PropertyNotFoundException(
                'Property "nonExistentProperty" not found.',
                $obj,
                'nonExistentProperty'
            ))
            ->shouldBeCalled();
        $this->shouldThrow('\PhpSpec\Exception\Fracture\PropertyNotFoundException')
            ->duringSet('nonExistentProperty', 'any value');
    }

    function it_delegates_throwing_calling_method_on_non_object_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $exceptions->callingMethodOnNonObject('foo')
            ->willReturn(new \PhpSpec\Exception\Wrapper\SubjectException(
                'Call to a member function "foo()" on a non-object.'
            ))
            ->shouldBeCalled();
        $this->shouldThrow('\PhpSpec\Exception\Wrapper\SubjectException')
            ->duringCall('foo');
    }

    function it_delegates_throwing_setting_property_on_non_object_exception(WrappedObject $wrappedObject, ExceptionFactory $exceptions)
    {
        $exceptions->settingPropertyOnNonObject('foo')
            ->willReturn(new \PhpSpec\Exception\Wrapper\SubjectException(
                'Setting property "foo" on a non-object.'
            ))
            ->shouldBeCalled();
        $this->shouldThrow('\PhpSpec\Exception\Wrapper\SubjectException')
            ->duringSet('foo');
    }
}
