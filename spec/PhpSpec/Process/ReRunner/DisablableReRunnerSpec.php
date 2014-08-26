<?php

namespace spec\PhpSpec\Process\ReRunner;

use PhpSpec\Console\IO;
use PhpSpec\ObjectBehavior;
use PhpSpec\Process\ReRunner;
use Prophecy\Argument;

class DisablableReRunnerSpec extends ObjectBehavior
{
    function let(IO $io, ReRunner $decoratedReRunner)
    {
        $this->beconstructedWith($decoratedReRunner, $io);
    }

    function it_is_not_supported_if_child_is_not_supported(ReRunner $decoratedReRunner)
    {
        $decoratedReRunner->isSupported()->willReturn(false);

        $this->isSupported()->shouldReturn(false);
    }

    function it_is_supported_if_child_is_supported(ReRunner $decoratedReRunner)
    {
        $decoratedReRunner->isSupported()->willReturn(true);

        $this->isSupported()->shouldReturn(true);
    }

    function it_reruns_the_suite_if_it_is_enabled_in_the_config(IO $io, ReRunner $decoratedReRunner)
    {
        $io->isRerunEnabled()->willReturn(true);

        $this->reRunSuite();

        $decoratedReRunner->reRunSuite()->shouldHaveBeenCalled();
    }

    function it_does_not_rerun_the_suite_if_it_is_disabled_in_the_config(IO $io, ReRunner $decoratedReRunner)
    {
        $io->isRerunEnabled()->willReturn(false);

        $this->reRunSuite();

        $decoratedReRunner->reRunSuite()->shouldNotHaveBeenCalled();
    }
}
