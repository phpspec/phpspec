<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface;

class ConstructorDecoratorSpec extends ObjectBehavior
{
    function let(ExpectationInterface $expectation)
    {
        $this->beConstructedWith($expectation);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface');
    }


}
