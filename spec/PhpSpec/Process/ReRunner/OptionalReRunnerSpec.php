<?php

namespace spec\PhpSpec\Process\ReRunner;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\ObjectBehavior;
use PhpSpec\Process\ReRunner;

class OptionalReRunnerSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, ReRunner $decoratedReRunner)
    {
        $this->beconstructedWith($decoratedReRunner, $io);
    }

    function it_reruns_the_suite_if_it_is_enabled_in_the_config(ConsoleIO $io, ReRunner $decoratedReRunner)
    {
        $io->isRerunEnabled()->willReturn(true);

        $this->reRunSuite();

        $decoratedReRunner->reRunSuite()->shouldHaveBeenCalled();
    }

    function it_does_not_rerun_the_suite_if_it_is_disabled_in_the_config(ConsoleIO $io, ReRunner $decoratedReRunner)
    {
        $io->isRerunEnabled()->willReturn(false);

        $this->reRunSuite();

        $decoratedReRunner->reRunSuite()->shouldNotHaveBeenCalled();
    }
}
