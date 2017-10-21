<?php

namespace spec\PhpSpec\CodeGenerator\Generator\Argument;

use PhpSpec\CodeGenerator\Generator\Argument\Argument;
use PhpSpec\CodeGenerator\Generator\Argument\StringConverter;
use PhpSpec\Event\PhpSpecEvent;
use PhpSpec\ObjectBehavior;

/**
 * @mixin StringConverter
 */
class StringConverterSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(StringConverter::class);
    }

    function it_builds_empty_string_when_there_are_no_arguments()
    {
        $this->convertFromArguments([], __NAMESPACE__)->shouldReturn('');
    }

    function it_builds_an_argument_string_for_non_type_hinted_arguments()
    {
        $arguments = [
            new Argument('bar', ''),
            new Argument('baz', '')
        ];

        $this->convertFromArguments($arguments, __NAMESPACE__)->shouldReturn('$bar, $baz');
    }

    function it_builds_an_argument_string_for_type_hinted_arguments()
    {
        $arguments = [
            new Argument('iterator', new \ReflectionClass(\Iterator::class)),
            new Argument('array', new \ReflectionClass(\SplFixedArray::class)),
        ];

        $this->convertFromArguments($arguments, __NAMESPACE__)->shouldReturn('\Iterator $iterator, \SplFixedArray $array');
    }

    function it_builds_an_argument_string_for_a_nullable_type_hinted_argument()
    {
        $iteratorArg = new Argument('iterator', new \ReflectionClass(\Iterator::class));
        $iteratorArg->setDefaultValue(null);

        $this->convertFromArguments([$iteratorArg], __NAMESPACE__)->shouldReturn('\Iterator $iterator = null');
    }

    function it_builds_an_argument_string_for_scalar_type_hinted_arguments()
    {
        $arguments = [
            new Argument('message', 'string'),
            new Argument('option', 'int'),
        ];

        $this->convertFromArguments($arguments, __NAMESPACE__)->shouldReturn('string $message, int $option');
    }

    function it_builds_an_argument_string_for_classes_in_the_same_namespace()
    {
        $arguments = [
            new Argument('bar', new \ReflectionClass(Bar::class))
        ];

        $this->convertFromArguments($arguments, __NAMESPACE__)->shouldReturn('Bar $bar');
    }

    function it_builds_an_argument_string_for_classes_in_a_different_namespace()
    {
        $parameters = [
            new Argument('event', new \ReflectionClass(PhpSpecEvent::class))
        ];

        $this->convertFromArguments($parameters, __NAMESPACE__)->shouldReturn('\PhpSpec\Event\PhpSpecEvent $event');
    }
}

class Bar
{
}
