<?php

namespace spec\PhpSpec\Formatter\Presenter;

use PhpSpec\ObjectBehavior;
use PhpSpec\Formatter\Presenter\Differ\Differ;

class StringPresenterSpec extends ObjectBehavior
{
    function let(Differ $differ)
    {
        $this->beConstructedWith($differ);
    }

    function it_presents_short_string_in_quotes()
    {
        $this->presentValue('some_string')->shouldReturn('"some_string"');
    }

    function it_presents_long_string_in_quotes_but_trimmed()
    {
        $this->presentValue('some_string_longer_than_twenty_five_chars')
            ->shouldReturn('"some_string_longer_than_t..."');
    }

    function it_presents_only_first_line_of_multiline_string()
    {
        $this->presentValue("some\nmultiline\nvalue")->shouldReturn('"some..."');
    }

    function it_presents_simple_type_as_typed_value()
    {
        $this->presentValue(42)->shouldReturn('[integer:42]');
        $this->presentValue(42.0)->shouldReturn('[double:42]');
    }

    function it_presents_object_as_classname()
    {
        $this->presentValue(new \stdClass())->shouldReturn('[obj:stdClass]');
    }

    function it_presents_array_as_elements_count()
    {
        $this->presentValue(array(1, 2, 3))->shouldReturn('[array:3]');
    }

    function it_presents_boolean_as_string()
    {
        $this->presentValue(true)->shouldReturn('true');
        $this->presentValue(false)->shouldReturn('false');
    }

    function it_presents_closure_as_type()
    {
        $this->presentValue(function () {})->shouldReturn('[closure]');
    }

    function it_presents_exception_as_class_with_constructor()
    {
        $this->presentValue(new \RuntimeException('message'))
            ->shouldReturn('[exc:RuntimeException("message")]');
    }

    function it_presents_function_callable_as_string()
    {
        $this->presentValue('date')
            ->shouldReturn('[date()]');
    }

    function it_presents_method_as_string(WithMethod $object)
    {
        $className = get_class($object->getWrappedObject());
        $this->presentValue(array($object, 'specMethod'))
            ->shouldReturn(sprintf('[obj:%s]::specMethod()', $className));
    }

    function it_presents_magic_method_as_string(WithMagicCall $object)
    {
        $className = get_class($object->getWrappedObject());
        $this->presentValue(array($object, 'undefinedMethod'))
            ->shouldReturn(sprintf('[obj:%s]::undefinedMethod()', $className));
    }

    function it_presents_static_method_as_string(WithMethod $object)
    {
        $className = get_class($object->getWrappedObject());
        $this->presentValue(array($className, 'specMethod'))
            ->shouldReturn(sprintf('%s::specMethod()', $className));
    }

    function it_presents_static_magic_method_as_string()
    {
        $className = __NAMESPACE__ . '\\WithStaticMagicCall';
        $this->presentValue(array($className, 'undefinedMethod'))
            ->shouldReturn(sprintf('%s::undefinedMethod()', $className));
    }

    function it_presents_invokable_object_as_string(WithMagicInvoke $object)
    {
        $className = get_class($object->getWrappedObject());
        $this->presentValue($object)
            ->shouldReturn(sprintf('[obj:%s]', $className));
    }

    function it_presents_string_as_string()
    {
        $this->presentString('some string')->shouldReturn('some string');
    }

    function its_presentValue_displays_invokable_objects_as_objects()
    {
        $invokable = new ObjectBehavior();
        $invokable->setSpecificationSubject($this);
        $this->presentValue($invokable)->shouldReturn('[obj:PhpSpec\Formatter\Presenter\StringPresenter]');
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
