<?php

namespace spec\PhpSpec\Process\Prerequisites;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Context\ExecutionContext;
use Prophecy\Argument;

class SuitePrerequisitesSpec extends ObjectBehavior
{
    function let(ExecutionContext $executionContext)
    {
        $this->beConstructedWith($executionContext);
    }

    function it_does_nothing_when_types_exist(ExecutionContext $executionContext)
    {
        $executionContext->getGeneratedTypes()->willReturn(array('stdClass'));

        $this->guardPrerequisites();
    }

    function it_throws_execption_when_types_do_not_exist(ExecutionContext $executionContext)
    {
        $executionContext->getGeneratedTypes()->willReturn(array('stdClassXXX'));

        $this->shouldThrow('PhpSpec\Process\Prerequisites\PrerequisiteFailedException')->duringGuardPrerequisites();
    }
}
