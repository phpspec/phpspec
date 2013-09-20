<?php

namespace spec\PhpSpec\Formatter\Presenter\Differ;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\Formatter\Presenter\Differ\EngineInterface;

class ObjectEngineSpec extends ObjectBehavior
{
    function let(EngineInterface $engine)
    {
        $this->beConstructedWith($engine);
    }

    function it_is_a_diff_engine()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Formatter\Presenter\Differ\EngineInterface');
    }

    function it_supports_objects()
    {
        $this->supports(new \stdClass, new \stdClass)->shouldReturn(true);
    }

    function it_does_not_support_anything_else()
    {
        $this->supports(new \stdClass, 'not an object')->shouldReturn(false);
    }

    function it_compares_print_r_string_results_by_default($engine, \stdClass $object)
    {
        $arg = Argument::that(function($value) {
            return false !== strpos($value, '*RECURSION*'); // print_r proof
        });
        $engine->compare($arg, $arg)->willReturn('<diff>');

        $this->compare($object, $object)->shouldReturn('<diff>');
    }

    function it_compares_results_with_custom_callable($engine, \stdClass $object)
    {
        $this->beConstructedWith($engine, function($object) { return 'object_diff'; });
        $engine->compare('object_diff', 'object_diff')->willReturn('<diff>');

        $this->compare($object, $object)->shouldReturn('<diff>');
    }

    function it_should_not_allow_non_callable_diffSource($engine, \stdClass $object)
    {
        $this->shouldThrow(new \InvalidArgumentException('Argument 2 passed to ObjectEngine::__construct must be a callable, string given.'))->during('__construct', array($engine, 'not callable'));
    }
}
