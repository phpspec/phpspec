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

    function it_can_be_instantiated_with_a_factory_method()
    {
        $this->callOnWrappedObject(
            'beConstructedThrough',
            array(
                '\DateTime::createFromFormat',
                array('d-m-Y', '01-01-1970')
            )
        );
        $this->instantiate()->shouldHaveType('\DateTime');
    }

    function it_can_be_instantiated_with_a_factory_method_with_method_name_only()
    {
        $this->callOnWrappedObject('beAnInstanceOf', array('\DateTime'));
        $this->callOnWrappedObject(
            'beConstructedThrough',
            array(
                'createFromFormat',
                array('d-m-Y', '01-01-1970')
            )
        );
        $this->instantiate()->shouldHaveType('\DateTime');
    }
    
    function it_can_reset_construct_arguments()
    {
        $this->callOnWrappedObject('beAnInstanceOf', array('\DateTime'));
        $this->callOnWrappedObject('beConstructedWith', array(array('07-09-1983')));
        $this->instantiate()->format('Y-m-d')->shouldBe('1983-09-07');
        
        $this->callOnWrappedObject('beConstructedWith', array(array('07-06-1983')));
        $this->instantiate()->format('Y-m-d')->shouldBe('1983-06-07');
    }

    function it_can_reset_construct_arguments_with_a_factory_method()
    {
        $this->callOnWrappedObject('beAnInstanceOf', array('\DateTime'));
        $this->callOnWrappedObject(
            'beConstructedThrough',
            array(
                'createFromFormat',
                array('d-m-Y', '07-09-1983')
            )
        );
        $this->instantiate()->format('Y-m-d')->shouldBe('1983-09-07');
        
        $this->callOnWrappedObject(
            'beConstructedThrough',
            array(
                'createFromFormat',
                array('d-m-Y', '07-06-1983')
            )
        );
        $this->instantiate()->format('Y-m-d')->shouldBe('1983-06-07');
    }
}
