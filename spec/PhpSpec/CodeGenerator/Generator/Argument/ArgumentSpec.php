<?php

namespace spec\PhpSpec\CodeGenerator\Generator\Argument;

use PhpSpec\CodeGenerator\Generator\Argument\Argument;
use PhpSpec\ObjectBehavior;

class ArgumentSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('', '');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Argument::class);
    }

    function it_can_return_argument_information()
    {
        $this->beConstructedWith('code', 'int');

        $this->getName()->shouldBe('code');
        $this->getType()->shouldBe('int');
    }

    function it_can_have_a_reflection_class_type()
    {
        $reflectionClass = new \ReflectionClass(\Prophecy\Argument::class);
        $this->beConstructedWith('code', $reflectionClass);

        $this->getType()->shouldBe($reflectionClass);
    }

    function it_has_no_default_value_by_default()
    {
        $this->hasDefaultValue()->shouldBe(false);
    }

    function it_has_a_default_value_when_one_is_set()
    {
        $this->setDefaultValue(false);
        $this->shouldHaveDefaultValue();
        $this->getDefaultValue()->shouldBe(false);
    }
}
