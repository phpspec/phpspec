<?php

namespace spec\PhpSpec\Message;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SpecSpec extends ObjectBehavior {
    function it_is_initializable() {
        $this->shouldHaveType('PhpSpec\Message\Spec');
    }

    function it_should_implement_the_Spec_Message_Interface() {
        $this->shouldHaveType('PhpSpec\Message\SpecInterface');
    }
}
