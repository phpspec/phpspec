<?php

namespace spec\PhpSpec\Process\ReRunner;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\ReRunner;
use Prophecy\Argument;

class CompositeReRunnerSpec extends ObjectBehavior
{
    function let(ReRunner $reRunner1, ReRunner $reRunner2)
    {
        $this->beConstructedWith(
            array(
                $reRunner1->getWrappedObject(),
                $reRunner2->getWrappedObject()
            )
        );
    }

    function it_is_a_rerunner()
    {
        $this->shouldHaveType('PhpSpec\Process\ReRunner');
    }

    function it_is_unsupported_if_all_child_rerunners_are_unsupported(ReRunner $reRunner1, ReRunner $reRunner2)
    {
        $reRunner1->isSupported()->willReturn(false);
        $reRunner2->isSupported()->willReturn(false);

        $this->isSupported()->shouldReturn(false);
    }

    function it_is_supported_if_any_child_is_supported(ReRunner $reRunner1)
    {
        $reRunner1->isSupported()->willReturn(true);

        $this->isSupported()->shouldReturn(true);
    }

    function it_invokes_the_first_supported_child_to_rerun_the_suite(ReRunner $reRunner1)
    {
        $reRunner1->isSupported()->willReturn(true);
        $reRunner1->reRunSuite()->shouldBeCalled();

        $this->reRunSuite();
    }
}
