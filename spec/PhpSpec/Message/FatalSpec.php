<?php

namespace spec\PhpSpec\Message;

use PhpSpec\Message\Fatal;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class FatalSpec extends ObjectBehavior {

    function it_is_initializable() {
        $this->shouldHaveType('PhpSpec\Message\Fatal');
    }

    function it_should_implement_the_Message_Interface() {
        $this->shouldHaveType('PhpSpec\Message\MessageInterface');
    }

    function it_should_set_a_message()
    {
      $message = new Fatal();
      $message->setMessage('test');
      expect($message->getMessage())->toBe('test');
    }

    function it_should_be_null_on_construction()
    {
      $message = new Fatal();
      expect($message->getMessage())->toBe(null);
    }

}
