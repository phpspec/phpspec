<?php

namespace spec\PhpSpec\Runner;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\Presenter;

use ReflectionFunction;
use ReflectionParameter;
use stdClass;

class CollaboratorManagerSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $this->beConstructedWith($presenter);
        $presenter->presentString(Argument::cetera())->willReturn('someString');
    }

    function it_stores_collaborators_by_name($collaborator)
    {
        $this->set('custom_collaborator', $collaborator);
        $this->get('custom_collaborator')->shouldReturn($collaborator);
    }

    function it_provides_a_method_to_check_if_collaborator_exists($collaborator)
    {
        $this->set('custom_collaborator', $collaborator);

        $this->has('custom_collaborator')->shouldReturn(true);
        $this->has('nonexistent')->shouldReturn(false);
    }

    function it_throws_CollaboratorException_on_attempt_to_get_unexisting_collaborator()
    {
        $this->shouldThrow('PhpSpec\Exception\Wrapper\CollaboratorException')
            ->duringGet('nonexistent');
    }

    function it_creates_function_arguments_for_ReflectionFunction(
        ReflectionFunction $function, ReflectionParameter $param1, ReflectionParameter $param2
    ) {
        $this->set('arg1', $arg1 = new stdClass);
        $this->set('arg2', new stdClass);
        $this->set('arg3', $arg3 = new stdClass);

        $function->getParameters()->willReturn(array($param1, $param2));
        $param1->getName()->willReturn('arg1');
        $param2->getName()->willReturn('arg3');

        $this->getArgumentsFor($function)->shouldReturn([$arg1, $arg3]);
    }

    function it_creates_null_function_arguments_for_ReflectionFunction_if_no_collaborator_found(
        ReflectionFunction $function, ReflectionParameter $param1, ReflectionParameter $param2
    ) {
        $this->set('arg1', new stdClass);
        $this->set('arg2', new stdClass);
        $this->set('arg3', $arg3 = new stdClass);

        $function->getParameters()->willReturn(array($param1, $param2));
        $param1->getName()->willReturn('arg4');
        $param2->getName()->willReturn('arg3');

        $this->getArgumentsFor($function)->shouldReturn(array(null, $arg3));
    }
}
