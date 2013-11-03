<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Unwrapper;
use Prophecy\Argument;

use PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface;

class ConstructorDecoratorSpec extends ObjectBehavior
{
    function let(ExpectationInterface $expectation, Unwrapper $unwrapper)
    {
        $this->beConstructedWith($expectation, $unwrapper);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface');
    }


}
