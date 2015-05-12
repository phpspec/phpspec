<?php

namespace spec\PhpSpec\Message;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ExampleSpec extends ObjectBehavior {
    function it_is_initializable() {
        $this->shouldHaveType('PhpSpec\Message\Example');
    }

    function it_should_implement_the_Example_Message_Interface() {
        $this->shouldHaveType('PhpSpec\Message\ExampleInterface');
    }

}
