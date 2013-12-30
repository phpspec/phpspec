<?php

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\PresenterInterface;

class WrappedObjectSpec extends ObjectBehavior
{
    function let(PresenterInterface $presenter)
    {
        $this->beConstructedWith(null, $presenter);
    }

    function it_instantiates_object_using_classname()
    {
        $this->callOnWrappedObject('beAnInstanceOf', array('ArrayObject'));
        $this->instantiate()->shouldHaveType('ArrayObject');
    }

    function it_keeps_instantiated_object()
    {
        $this->callOnWrappedObject('beAnInstanceOf', array('ArrayObject'));
        $this->instantiate()->shouldBeEqualTo($this->getInstance());
    }

    function it_instantiate_object_with_arguments()
    {
        $this->callOnWrappedObject('beAnInstanceOf', array('ArrayObject'));
        $this->instantiate(array(array(1,2,3)))->shouldBeLike(new \ArrayObject(array(1,2,3)));
    }
}
