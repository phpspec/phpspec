<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\NamedConstructorNotFoundException;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class NamedConstructorNotFoundListenerSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, ResourceManager $resourceManager, GeneratorManager $generatorManager,
                 SuiteEvent $suiteEvent, ExampleEvent $exampleEvent, Resource $resource)
    {
        $io->writeln(Argument::cetera())->willReturn();
        $io->askConfirmation(Argument::any())->willReturn();
        $resourceManager->createResource(Argument::any())->willReturn($resource);
        $generatorManager->generate($resource, Argument::cetera())->willReturn();

        $this->beConstructedWith($io, $resourceManager, $generatorManager);
    }

    function it_does_not_prompt_for_method_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_non_namedconstructornotfoundexception_was_thrown($exampleEvent, $suiteEvent, $io, \InvalidArgumentException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_prompts_for_method_generation_if_namedconstructornotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
    
    function it_generates_method_if_namedconstructornotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, $generatorManager, $resource, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(true);
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $generatorManager->generate($resource, Argument::cetera())->shouldHaveBeenCalled();
    }
    
    function it_notifies_the_user_when_it_generated_method($exampleEvent, $suiteEvent, $io, $generatorManager, $resource, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(true);
        $generatorManager->generate($resource, Argument::cetera())->willReturn($message = 'Non-empty string');
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->writeln($message, Argument::cetera())->shouldHaveBeenCalled();
    }
    
    function it_doesnt_output_empty_string_when_generator_has_no_output($exampleEvent, $suiteEvent, $io, $generatorManager, $resource, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(true);
        $generatorManager->generate($resource, Argument::cetera())->willReturn($message = '');
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->writeln($message, Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
