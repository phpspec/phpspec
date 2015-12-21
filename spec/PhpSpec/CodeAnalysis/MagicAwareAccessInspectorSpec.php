<?php

namespace spec\PhpSpec\CodeAnalysis;

use Phpspec\CodeAnalysis\AccessInspector;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class MagicAwareAccessInspectorSpec extends ObjectBehavior
{
    function let(AccessInspector $accessInspector)
    {
        $this->beConstructedWith($accessInspector);
    }

    function it_should_be_an_access_inspector()
    {
        $this->shouldImplement('PhpSpec\CodeAnalysis\AccessInspector');
    }

    function it_should_detect_a_magic_getter_if_no_value_is_given()
    {
        $this->isPropertyReadable(new ObjectWithMagicGet, 'property')->shouldReturn(true);
    }

    function it_should_detect_a_magic_setter_if_a_value_is_given()
    {
        $this->isPropertyWritable(new ObjectWithMagicSet, 'property', true)->shouldReturn(true);
    }

    function it_should_detect_a_magic_call_method()
    {
        $this->isMethodCallable(new ObjectWithMagicCall, 'method')->shouldreturn(true);
    }

    function it_should_not_detect_a_getter_if_there_is_no_magic_getter_and_wrapped_inspector_finds_none(AccessInspector $accessInspector)
    {
        $accessInspector->isPropertyReadable(new \StdClass(), 'foo')->willReturn(false);

        $this->isPropertyReadable(new \StdClass(), 'foo')->shouldReturn(false);
    }

    function it_should_detect_a_getter_if_there_is_no_magic_getter_but_wrapped_inspector_finds_one(AccessInspector $accessInspector)
    {
        $accessInspector->isPropertyReadable(new \StdClass(), 'foo')->willReturn(true);

        $this->isPropertyReadable(new \StdClass(), 'foo')->shouldReturn(true);
    }

    function it_should_not_detect_a_setter_if_there_is_no_magic_setter_and_wrapped_inspector_finds_none(AccessInspector $accessInspector)
    {
        $accessInspector->isPropertyWritable(new \StdClass(), 'foo')->willReturn(false);

        $this->isPropertyWritable(new \StdClass(), 'foo')->shouldReturn(false);
    }

    function it_should_detect_a_setter_if_there_is_no_magic_setter_but_wrapped_inspector_finds_one(AccessInspector $accessInspector)
    {
        $accessInspector->isPropertyWritable(new \StdClass(), 'foo')->willReturn(true);

        $this->isPropertyWritable(new \StdClass(), 'foo')->shouldReturn(true);
    }

    function it_should_detect_a_method_if_there_is_no_magic_caller_and_wrapped_inspector_finds_none(AccessInspector $accessInspector)
    {
        $accessInspector->isMethodCallable(new \StdClass(), 'foo')->willReturn(false);

        $this->isMethodCallable(new \StdClass(), 'foo')->shouldReturn(false);
    }

    function it_should_detect_a_method_if_there_is_no_magic_caller_but_wrapped_inspector_finds_one(AccessInspector $accessInspector)
    {
        $accessInspector->isMethodCallable(new \StdClass(), 'foo')->willReturn(true);

        $this->isMethodCallable(new \StdClass(), 'foo')->shouldReturn(true);
    }
}

class ObjectWithMagicGet
{
    public function __get($name)
    {
    }
}

class ObjectWithMagicSet
{
    public function __set($name, $value)
    {
    }
}

class ObjectWithMagicCall
{
    public function __call($name, $args)
    {
    }
}
