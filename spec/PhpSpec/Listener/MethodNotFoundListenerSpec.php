<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Console\IO,
    PhpSpec\Locator\ResourceManager,
    PhpSpec\CodeGenerator\GeneratorManager,
    PhpSpec\Event\ExampleEvent,
    PhpSpec\Event\SuiteEvent,
    PhpSpec\Exception\Fracture\MethodNotFoundException;

class MethodNotFoundListenerSpec extends ObjectBehavior
{
    function let(IO $io, ResourceManager $resourceManager, GeneratorManager $generatorManager, 
                 SuiteEvent $suiteEvent, ExampleEvent $exampleEvent, MethodNotFoundException $exception)
    {
        $io->writeln(Argument::any())->willReturn();
        $io->askConfirmation(Argument::any())->willReturn();

        $exception->getSubject()->willReturn(new \StdClass);
        $exception->getMethodName()->willReturn('someMethod');
        $exception->getArguments()->willReturn(array());
        
        $this->beConstructedWith($io, $resourceManager, $generatorManager);
    }
    
    function it_does_not_prompt_for_method_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
    
    function it_does_not_prompt_for_method_generation_if_non_methodnotfoundexception_was_thrown($exampleEvent, $suiteEvent, $io, \InvalidArgumentException $otherException)
    {
        $exampleEvent->getException()->willReturn($otherException);
        $io->isCodeGenerationEnabled()->willReturn(true);
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }  
      
    function it_prompts_for_method_generation_if_methodnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }
      
    function it_does_not_prompt_for_method_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, $exception)
    {
        $io->isCodeGenerationEnabled()->willReturn(false);
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
	
	function it_does_not_prompt_for_method_generation_if_flags_are_set_but_method_exists_and_is_private($exampleEvent, $suiteEvent, $io, $exception)
	{	
		$exception->getSubject()->willReturn(new ClassWithPrivateMethod());
		$exception->getMethodName()->willReturn('privateMethod');
		
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
	}
}

class ClassWithPrivateMethod
{
	private function privateMethod(){}
}
