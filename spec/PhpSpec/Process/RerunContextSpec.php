<?php

namespace spec\PhpSpec\Process;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RerunContextSpec extends ObjectBehavior
{
    function it_remembers_fatal_specs()
    {
        $this->setFatalSpec('MySpec', 'func', array('This went wrong'));
        $this->wasFatalSpec('MySpec', 'func')->shouldReturn(true);
    }

    function it_remembers_errors_of_fatal_specs()
    {
        $this->setFatalSpec('MySpec', 'func', array('This went wrong'));
        $this->getFatalSpecError('MySpec', 'func')->shouldReturn(array('This went wrong'));
    }

    function it_serialises_itself_to_string()
    {
        $this->setFatalSpec('MySpec', 'func', array('This went wrong'));
        $this->asString()->shouldReturn('{"fatals":{"MySpec":{"func":["This went wrong"]}}}');
    }

    function it_deserialises_itself_from_string()
    {
        $this->beConstructedThrough('fromString', array('{"fatals":{"MySpec":{"func":["This went wrong"]}}}'));
        $this->wasFatalSpec('MySpec', 'func')->shouldReturn(true);
    }

    function it_exposes_list_of_fatals()
    {
        $this->setFatalSpec('MySpec', 'func', array('This went wrong'));

        $this->listFatalSpecs()->shouldReturn(
            array(
                array('MySpec', 'func')
            )
        );
    }
}
