<?php

namespace spec\PhpSpec\Message;

use PhpSpec\Message\Example;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ExampleSpec extends ObjectBehavior {

    function it_is_initializable() {
        $this->shouldHaveType('PhpSpec\Message\Example');
    }

    function it_should_implement_the_Example_Message_Interface() {
        $this->shouldHaveType('PhpSpec\Message\ExampleInterface');
    }

    function it_should_set_a_message()
    {
      $message = new Example();
      $message->setExampleMessage('test');
      expect($message->getExampleMessage())->toBe('test');
    }

    function it_should_be_null_on_construction()
    {
      $message = new Example();
      expect($message->getExampleMessage())->toBe(null);
    }

}
