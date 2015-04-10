<?php

namespace spec\PhpSpec\CodeAnalysis;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class MagicAwareAccessInspectorSpec extends ObjectBehavior
{
    function it_should_be_an_access_inspector()
    {
        $this->shouldImplement('PhpSpec\CodeAnalysis\AccessInspectorInterface');
    }

    function it_should_fail_to_check_for_a_property_of_a_non_object()
    {
        $this->isPropertyAccessible('foo', 'property')->shouldReturn(false);
    }

    function it_should_detect_a_magic_getter_if_no_value_is_given()
    {
        $this->isPropertyAccessible(new ObjectWithMagicGet, 'property')->shouldReturn(true);
    }

    function it_should_detect_a_magic_setter_if_a_value_is_given()
    {
        $this->isPropertyAccessible(new ObjectWithMagicSet, 'property', true)->shouldReturn(true);
    }

    function it_should_reject_an_object_if_the_property_does_not_exist()
    {
        $this->isPropertyAccessible(new ObjectWithNoProperty, 'property')->shouldReturn(false);
    }

    function it_should_reject_a_private_property()
    {
        $this->isPropertyAccessible(new ObjectWithPrivateProperty, 'property')->shouldReturn(false);
    }

    function it_should_detect_a_public_property()
    {
        $this->isPropertyAccessible(new ObjectWithPublicProperty, 'property')->shouldReturn(true);
    }

    function it_should_fail_to_check_for_a_method_of_a_non_object()
    {
        $this->isMethodAccessible('foo', 'method')->shouldReturn(false);
    }

    function it_should_detect_a_magic_call_method()
    {
        $this->isMethodAccessible(new ObjectWithMagicCall, 'method')->shouldreturn(true);
    }

    function it_should_reject_an_object_if_a_method_does_not_exist()
    {
        $this->isMethodAccessible(new ObjectWithNoMethod, 'method')->shouldReturn(false);
    }

    function it_should_reject_a_private_method()
    {
        $this->isMethodAccessible(new ObjectWithPrivateMethod, 'method')->shouldReturn(false);
    }

    function it_should_detect_a_public_method()
    {
        $this->isMethodAccessible(new ObjectWithPublicMethod, 'method')->shouldReturn(true);
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

class ObjectWithNoProperty
{
}

class ObjectWithPrivateProperty
{
    private $property;
}

class ObjectWithPublicProperty
{
    public $property;
}

class ObjectWithMagicCall
{
    public function __call($name, $args)
    {
    }
}

class ObjectWithNoMethod
{
}

class ObjectWithPrivateMethod
{
    private function method()
    {
    }
}

class ObjectWithPublicMethod
{
    public function method()
    {
    }
}
