<?php

namespace spec\PhpSpec\CodeAnalysis;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class VisibilityAccessInspectorSpec extends ObjectBehavior
{
    function it_should_be_an_access_inspector()
    {
        $this->shouldImplement('PhpSpec\CodeAnalysis\AccessInspectorInterface');
    }

    function it_should_reject_an_object_if_the_property_does_not_exist()
    {
        $this->isPropertyReadable(new ObjectWithNoProperty, 'property')->shouldReturn(false);
        $this->isPropertyWritable(new ObjectWithNoProperty, 'property')->shouldReturn(false);
    }

    function it_should_reject_a_private_property()
    {
        $this->isPropertyReadable(new ObjectWithPrivateProperty, 'property')->shouldReturn(false);
        $this->isPropertyWritable(new ObjectWithPrivateProperty, 'property')->shouldReturn(false);
    }

    function it_should_detect_a_public_property()
    {
        $this->isPropertyReadable(new ObjectWithPublicProperty, 'property')->shouldReturn(true);
        $this->isPropertyWritable(new ObjectWithPublicProperty, 'property')->shouldReturn(true);
    }

    function it_should_reject_an_object_if_a_method_does_not_exist()
    {
        $this->isMethodCallable(new ObjectWithNoMethod, 'method')->shouldReturn(false);
    }

    function it_should_reject_a_private_method()
    {
        $this->isMethodCallable(new ObjectWithPrivateMethod, 'method')->shouldReturn(false);
    }

    function it_should_detect_a_public_method()
    {
        $this->isMethodCallable(new ObjectWithPublicMethod, 'method')->shouldReturn(true);
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
