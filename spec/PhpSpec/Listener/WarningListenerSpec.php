<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\ObjectBehavior;
use PhpSpec\Console\IO;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Event\SuiteEvent;

class WarningListenerSpec extends ObjectBehavior
{
    function let(IO $io)
    {
        $this->beConstructedWith($io);
    }

    function it_subscribes_to_events()
    {
        $this->getSubscribedEvents()->shouldReturn(
            array(
                'afterSpecification'  => array('afterSpecification', -10),
                'afterSuite'          => array('afterSuite', -10),
            )
        );
    }

    function it_outputs_warnings_after_suite_has_run(
        $io,
        SpecificationEvent $specEvent,
        SpecificationNode $node,
        SuiteEvent $suiteEvent
    ) {
        $node->getWarnings()->willReturn(array('warning 1', 'warning 2'));
        $specEvent->getSpecification()->willReturn($node);
        $this->afterSpecification($specEvent);

        $this->afterSuite($suiteEvent);

        $io->writeln('Warning: warning 1')->shouldHaveBeenCalled();
        $io->writeln('Warning: warning 2')->shouldHaveBeenCalled();
    }
}
