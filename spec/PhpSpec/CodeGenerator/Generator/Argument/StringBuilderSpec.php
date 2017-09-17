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

        $this->buildFromReflectionParameters($parameters, __NAMESPACE__)->shouldReturn('$bar, $baz');
    }

    function it_builds_an_argument_string_for_type_hinted_arguments()
    {
        $reflectionClass = new \ReflectionClass(Foo::class);
        $parameters = $reflectionClass->getMethod('typeHinted')->getParameters();

        $this->buildFromReflectionParameters($parameters, __NAMESPACE__)->shouldReturn('\Iterator $iterator, \SplFixedArray $array');
    }

    function it_builds_an_argument_string_for_a_nullable_type_hinted_argument()
    {
        $reflectionClass = new \ReflectionClass(Foo::class);
        $parameters = $reflectionClass->getMethod('typeHintedWithNullable')->getParameters();

        $this->buildFromReflectionParameters($parameters, __NAMESPACE__)->shouldReturn('\Iterator $iterator = null');
    }

    function it_builds_an_argument_string_for_scalar_type_hinted_arguments()
    {
        $reflectionClass = new \ReflectionClass(Foo::class);
        $parameters = $reflectionClass->getMethod('scalarTypeHinted')->getParameters();

        $this->buildFromReflectionParameters($parameters, __NAMESPACE__)->shouldReturn('string $message, int $option');
    }

    function it_builds_an_argument_string_for_classes_in_the_same_namespace()
    {
        $reflectionClass = new \ReflectionClass(Foo::class);
        $parameters = $reflectionClass->getMethod('sameNamespaceClassTypeHint')->getParameters();

        $this->buildFromReflectionParameters($parameters, __NAMESPACE__)->shouldReturn('Bar $bar');
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

    public function scalarTypeHinted(string $message, int $option)
    {
    }

    public function sameNamespaceClassTypeHint(Bar $bar)
    {
    }
}

class Bar
{
}
