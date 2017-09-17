<?php

namespace spec\PhpSpec\CodeGenerator\Generator\Argument;

use PhpSpec\CodeGenerator\Generator\Argument\StringBuilder;
use PhpSpec\ObjectBehavior;

/**
 * @mixin StringBuilder
 */
class StringBuilderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(StringBuilder::class);
    }

    function it_builds_an_argument_string_for_non_type_hinted_arguments()
    {
        $reflectionClass = new \ReflectionClass(Foo::class);
        $parameters = $reflectionClass->getMethod('nonTypeHinted')->getParameters();

        $this->buildFromReflectionParameters($parameters)->shouldReturn('$bar, $baz');
    }

    function it_builds_an_argument_string_for_type_hinted_arguments()
    {
        $reflectionClass = new \ReflectionClass(Foo::class);
        $parameters = $reflectionClass->getMethod('typeHinted')->getParameters();

        $this->buildFromReflectionParameters($parameters)->shouldReturn('\Iterator $iterator, \SplFixedArray $array');
    }
}

class Foo
{
    public function nonTypeHinted($bar, $baz)
    {
    }

    public function typeHinted(\Iterator $iterator, \SplFixedArray $array)
    {
    }

    public function typeHintedWithNullable(\Iterator $iterator = null)
    {
    }
}
