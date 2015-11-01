<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CallableTypePresenterSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $this->beConstructedWith($presenter);
    }

    function it_is_a_type_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Value\TypePresenter');
    }

    function it_should_support_callable_values()
    {
        $this->supports(function () {})->shouldReturn(true);
    }

    function it_should_present_a_closure()
    {
        $this->present(function () {})->shouldReturn('[closure]');
    }

    function it_should_present_function_callable_as_string()
    {
        $this->present('date')->shouldReturn('[date()]');
    }

    function it_should_present_a_method_as_string(
        WithMethod $object, Presenter $presenter
    ) {
        $className = get_class($object->getWrappedObject());

        $presenter->presentValue($object->getWrappedObject())->willReturn(sprintf('[obj:%s]', $className));

        $this->present(array($object, 'specMethod'))
            ->shouldReturn(sprintf('[obj:%s]::specMethod()', $className));
    }

    function it_should_present_a_magic_method_as_string(
        WithMagicCall $object, Presenter $presenter
    ) {
        $className = get_class($object->getWrappedObject());

        $presenter->presentValue($object->getWrappedObject())->willReturn(sprintf('[obj:%s]', $className));

        $this->present(array($object, 'undefinedMethod'))
            ->shouldReturn(sprintf('[obj:%s]::undefinedMethod()', $className));
    }

    function it_should_present_a_static_method_as_string(WithMethod $object)
    {
        $className = get_class($object->getWrappedObject());
        $this->present(array($className, 'specMethod'))
            ->shouldReturn(sprintf('%s::specMethod()', $className));
    }

    function it_should_present_a_static_magic_method_as_string()
    {
        $className = __NAMESPACE__ . '\\WithStaticMagicCall';
        $this->present(array($className, 'undefinedMethod'))
            ->shouldReturn(sprintf('%s::undefinedMethod()', $className));
    }

    function it_should_present_an_invokable_object_as_string(WithMagicInvoke $object)
    {
        $className = get_class($object->getWrappedObject());
        $this->present($object)->shouldReturn(sprintf('[obj:%s]', $className));
    }

}

class WithMethod
{
    function specMethod()
    {
    }
}

class WithStaticMethod
{
    function specMethod()
    {
    }
}

class WithMagicInvoke
{
    function __invoke()
    {
    }
}

class WithStaticMagicCall
{
    static function __callStatic($method, $name)
    {
    }
}

class WithMagicCall
{
    function __call($method, $name)
    {
    }
}
