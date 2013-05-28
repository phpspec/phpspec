<?php

namespace spec\PhpSpec\Runner;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use PhpSpec\SpecificationInterface;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Runner\Maintainer\MaintainerInterface;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Event\ExampleEvent;

use ReflectionClass;
use ReflectionMethod;

class ExampleRunnerSpec extends ObjectBehavior
{
    function let(EventDispatcherInterface $dispatcher, PresenterInterface $presenter)
    {
        $this->beConstructedWith($dispatcher, $presenter);
    }

    function it_executes_example_in_newly_created_context(
        ExampleNode $example, SpecificationNode $specification, ReflectionClass $specReflection,
        ReflectionMethod $exampReflection, SpecificationInterface $context
    )
    {
        $example->isPending()->willReturn(false);
        $example->getFunctionReflection()->willReturn($exampReflection);
        $example->getSpecification()->willReturn($specification);
        $specification->getClassReflection()->willReturn($specReflection);
        $specReflection->newInstanceArgs()->willReturn($context);

        $exampReflection->getParameters()->willReturn(array());
        $exampReflection->invokeArgs($context, array())->shouldBeCalled();

        $this->run($example);
    }

    function it_dispatches_ExampleEvent_with_pending_status_if_example_is_pending(
        EventDispatcherInterface $dispatcher,
        ExampleNode $example, SpecificationNode $specification, ReflectionClass $specReflection,
        SpecificationInterface $context, ReflectionMethod $exampReflection
    )
    {
        $example->isPending()->willReturn(true);
        $example->getSpecification()->willReturn($specification);
        $example->getFunctionReflection()->willReturn($exampReflection);
        $specification->getClassReflection()->willReturn($specReflection);
        $specReflection->newInstanceArgs()->willReturn($context);

        $dispatcher->dispatch('beforeExample', Argument::any())->shouldBeCalled();
        $dispatcher->dispatch('afterExample',
            Argument::which('getResult', ExampleEvent::PENDING)
        )->shouldBeCalled();

        $this->run($example);
    }

    function it_dispatches_ExampleEvent_with_failed_status_if_matcher_throws_exception(
        EventDispatcherInterface $dispatcher,
        ExampleNode $example, SpecificationNode $specification, ReflectionClass $specReflection,
        ReflectionMethod $exampReflection, SpecificationInterface $context
    )
    {
        $example->isPending()->willReturn(false);
        $example->getFunctionReflection()->willReturn($exampReflection);
        $example->getSpecification()->willReturn($specification);
        $specification->getClassReflection()->willReturn($specReflection);
        $specReflection->newInstanceArgs()->willReturn($context);

        $exampReflection->getParameters()->willReturn(array());
        $exampReflection->invokeArgs($context, array())
            ->willThrow('PhpSpec\Exception\Example\FailureException');

        $dispatcher->dispatch('beforeExample', Argument::any())->shouldBeCalled();
        $dispatcher->dispatch('afterExample',
            Argument::which('getResult', ExampleEvent::FAILED)
        )->shouldBeCalled();

        $this->run($example);
    }

    function it_dispatches_ExampleEvent_with_failed_status_if_example_throws_exception(
        EventDispatcherInterface $dispatcher,
        ExampleNode $example, SpecificationNode $specification, ReflectionClass $specReflection,
        ReflectionMethod $exampReflection, SpecificationInterface $context
    )
    {
        $example->isPending()->willReturn(false);
        $example->getFunctionReflection()->willReturn($exampReflection);
        $example->getSpecification()->willReturn($specification);
        $specification->getClassReflection()->willReturn($specReflection);
        $specReflection->newInstanceArgs()->willReturn($context);

        $exampReflection->getParameters()->willReturn(array());
        $exampReflection->invokeArgs($context, array())->willThrow('RuntimeException');

        $dispatcher->dispatch('beforeExample', Argument::any())->shouldBeCalled();
        $dispatcher->dispatch('afterExample',
            Argument::which('getResult', ExampleEvent::BROKEN)
        )->shouldBeCalled();

        $this->run($example);
    }

    function it_runs_all_supported_maintainers_before_and_after_each_example(
        ExampleNode $example, SpecificationNode $specification, ReflectionClass $specReflection,
        $context, ReflectionMethod $exampReflection, MaintainerInterface $maintainer,
        SpecificationInterface $context
    )
    {
        $example->isPending()->willReturn(false);
        $example->getFunctionReflection()->willReturn($exampReflection);
        $example->getSpecification()->willReturn($specification);
        $specification->getClassReflection()->willReturn($specReflection);
        $specReflection->newInstanceArgs()->willReturn($context);

        $exampReflection->getParameters()->willReturn(array());
        $exampReflection->invokeArgs(Argument::cetera())->willReturn(null);

        $maintainer->getPriority()->willReturn(1);
        $maintainer->supports($example)->willReturn(true);

        $maintainer->prepare($example, Argument::cetera())->shouldBeCalled();
        $maintainer->teardown($example, Argument::cetera())->shouldBeCalled();

        $this->registerMaintainer($maintainer);
        $this->run($example);
    }
}
