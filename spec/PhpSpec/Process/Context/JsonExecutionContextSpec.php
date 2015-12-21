<?php

namespace spec\PhpSpec\Process\Context;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class JsonExecutionContextSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedThrough('fromEnv', array(array('PHPSPEC_EXECUTION_CONTEXT' => '{"generated-types":[]}')));
    }

    function it_is_an_execution_context()
    {
        $this->shouldHaveType('PhpSpec\Process\Context\ExecutionContext');
    }

    function it_contains_no_generated_classes_when_created()
    {
        $this->getGeneratedTypes()->shouldReturn(array());
    }

    function it_remembers_what_classes_were_generated()
    {
        $this->addGeneratedType('PhpSpec\Foo');

        $this->getGeneratedTypes()->shouldReturn(array('PhpSpec\Foo'));
    }

    function it_can_be_serialized_as_env_array()
    {
        $this->addGeneratedType('PhpSpec\Foo');

        $this->asEnv()->shouldReturn(array('PHPSPEC_EXECUTION_CONTEXT' => '{"generated-types":["PhpSpec\\\\Foo"]}'));
    }
}
