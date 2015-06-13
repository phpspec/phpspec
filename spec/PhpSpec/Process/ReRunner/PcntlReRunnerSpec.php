<?php

namespace spec\PhpSpec\Process\ReRunner;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Context\ExecutionContextInterface;
use Prophecy\Argument;
use Symfony\Component\Process\PhpExecutableFinder;

class PcntlReRunnerSpec extends ObjectBehavior
{
    function let(PhpExecutableFinder $executableFinder, ExecutionContextInterface $executionContext)
    {
        $this->beConstructedThrough('withExecutionContext', array($executableFinder, $executionContext));
    }

    function it_is_a_rerunner()
    {
        $this->shouldHaveType('PhpSpec\Process\ReRunner');
    }

    function it_is_not_supported_when_php_process_is_not_found(PhpExecutableFinder $executableFinder)
    {
        $executableFinder->find()->willReturn(false);

        $this->isSupported()->shouldReturn(false);
    }
}
