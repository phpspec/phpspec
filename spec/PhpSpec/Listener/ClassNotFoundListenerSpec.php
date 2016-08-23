<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\ClassNotFoundException as PhpSpecClassException;

use Prophecy\Exception\Doubler\ClassNotFoundException as ProphecyClassException;

class ClassNotFoundListenerSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, ResourceManager $resourceManager, GeneratorManager $generatorManager,
                 SuiteEvent $suiteEvent, ExampleEvent $exampleEvent, Resource $resource)
    {
        $io->writeln(Argument::cetera())->willReturn();
        $io->askConfirmation(Argument::any())->willReturn();
        $resourceManager->createResource(Argument::any())->willReturn($resource);

        $this->beConstructedWith($io, $resourceManager, $generatorManager);
    }

    function it_does_not_prompt_for_class_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_does_not_prompt_for_class_generation_if_non_class_exception_was_thrown($exampleEvent, $suiteEvent, $io, \InvalidArgumentException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_prompts_for_class_generation_if_prophecy_classnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, ProphecyClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_prompts_for_method_generation_if_phpspec_classnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, PhpspecClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_for_class_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, PhpspecClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
    
    function it_generates_class_and_notifies_when_prophecy_classnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, $generatorManager, ProphecyClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(true);
        $generatorManager->generate(Argument::any(), "class")->willReturn($message = 'Non-empty string');
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->writeln($message, Argument::any())->shouldHaveBeenCalled();
    }
    
    function it_generates_class_and_notifies_when_phpspec_classnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, $generatorManager, PhpspecClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(true);
        $generatorManager->generate(Argument::any(), "class")->willReturn($message = 'Non-empty string');
        
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
        
        $io->writeln($message, Argument::any())->shouldHaveBeenCalled();
    }
    
    function it_does_not_output_an_empty_string_if_generator_has_no_output($exampleEvent, $suiteEvent, $io, $generatorManager, PhpspecClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(true);
        $generatorManager->generate(Argument::any(), "class")->willReturn('');
    
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
    
        $io->writeln('', Argument::any())->shouldNotHaveBeenCalled();
    }
}
