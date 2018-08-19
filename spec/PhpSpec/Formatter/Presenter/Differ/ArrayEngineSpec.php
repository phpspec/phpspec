<?php

namespace spec\PhpSpec\Formatter\Presenter\Differ;

use PhpSpec\Formatter\Presenter\Differ\DifferEngine;
use PhpSpec\ObjectBehavior;
use SebastianBergmann\Exporter\Exporter;
use stdClass;
use function var_dump;

class ArrayEngineSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new Exporter());
    }

    function it_is_a_diff_engine()
    {
        $this->shouldBeAnInstanceOf(DifferEngine::class);
    }

    function it_supports_arrays()
    {
        $this->supports([], [ 1, 2, 3 ])->shouldReturn(true);
    }

    function it_does_not_support_anything_else()
    {
        $this->supports('str', 2)->shouldReturn(false);
    }

    function it_compare_equal_arrays()
    {
        $result = $this->compare([ 1 ], [ 1 ]);
        $result->shouldBeString();
        $result->shouldNotContain('1');
    }

    function it_compare_array_of_objects_to_and_displays_its_properties()
    {
        $obj1 = new stdClass();
        $obj1->hash = '123#';
        $obj2 = new stdClass();
        $obj2->trash = '12345f';

        $diff = $this->compare([ $obj1 ], [ $obj2 ]);
        $diff->shouldContain('hash');
        $diff->shouldContain('123#');
        $diff->shouldContain('trash');
        $diff->shouldContain('12345f');
    }
}
