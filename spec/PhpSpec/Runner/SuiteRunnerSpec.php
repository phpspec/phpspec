<?php

namespace spec\PhpSpec\Runner;

use PhpSpec\Loader\ResourceLoader;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent,
    PhpSpec\Exception\Example\StopOnFailureException,
    PhpSpec\Loader\Suite,
    PhpSpec\Loader\Node\SpecificationNode,
    PhpSpec\Runner\SpecificationRunner;

use Symfony\Component\EventDispatcher\EventDispatcher;

class SuiteRunnerSpec extends ObjectBehavior
{
    
    function let(EventDispatcher $dispatcher, SpecificationRunner $specRunner, Suite $suite, 
                 SpecificationNode $spec1, SpecificationNode $spec2, ResourceLoader $loader)
    {
        $loader->load(Argument::cetera())->willReturn($suite);
        $this->beConstructedWith($dispatcher, $specRunner, $loader);

        $suite->getSpecifications()->willReturn( array($spec1, $spec2));
    }

    function it_gets_the_suite_from_the_loader_using_the_locator_variables($loader)
    {
        $this->run('somespec', 1000);

        $loader->load('somespec', 1000)->shouldHaveBeenCalled();
    }

    function it_runs_all_specs_in_the_suite_through_the_specrunner($specRunner, $spec1, $spec2)
    {
        $this->run(null, null);
        
        $specRunner->run($spec1)->shouldHaveBeenCalled();
        $specRunner->run($spec2)->shouldHaveBeenCalled();
    }
    
    function it_stops_running_subsequent_specs_when_a_spec_throws_a_StopOnFailureException($specRunner, $spec1, $spec2)
    {
        $specRunner->run($spec1)->willThrow(new StopOnFailureException());
        
        $this->run(null, null);
        
        $specRunner->run($spec2)->shouldNotBeenCalled();
    }
    
    function it_returns_a_successful_result_when_all_specs_in_suite_pass($specRunner, $spec1, $spec2)
    {
        $specRunner->run($spec1)->willReturn(ExampleEvent::PASSED);
        $specRunner->run($spec2)->willReturn(ExampleEvent::PASSED);
        
        $this->run(null, null)->shouldReturn(ExampleEvent::PASSED);
    }
    
    function it_returns_a_broken_result_when_one_spec_is_broken($specRunner, $spec1, $spec2)
    {
        $specRunner->run($spec1)->willReturn(ExampleEvent::FAILED);
        $specRunner->run($spec2)->willReturn(ExampleEvent::BROKEN);
        
        $this->run(null, null)->shouldReturn(ExampleEvent::BROKEN);
    }
    
    function it_returns_a_failed_result_when_one_spec_failed($specRunner, $spec1, $spec2)
    {
        $specRunner->run($spec1)->willReturn(ExampleEvent::FAILED);
        $specRunner->run($spec2)->willReturn(ExampleEvent::PENDING);
        
        $this->run(null, null)->shouldReturn(ExampleEvent::FAILED);
    }

    function it_dispatches_events_before_and_after_the_suite($dispatcher)
    {
        $this->run(null, null);
        
        $dispatcher->dispatch('beforeSuite',
            Argument::type('PhpSpec\Event\SuiteEvent')
        )->shouldHaveBeenCalled();

        $dispatcher->dispatch('afterSuite',
            Argument::type('PhpSpec\Event\SuiteEvent')
        )->shouldHaveBeenCalled();
    }
    
    function it_dispatches_afterSuite_event_with_result_and_time($specRunner, $dispatcher)
    {
        $specRunner->run(Argument::any())->willReturn(ExampleEvent::FAILED);
        
        $this->run(null, null);
        
        $dispatcher->dispatch('afterSuite',
            Argument::that( 
                function($event) { 
                    return ($event->getTime() > 0) 
                        && ($event->getResult() == ExampleEvent::FAILED);
                }
            )
        )->shouldHaveBeenCalled();
    }
}
