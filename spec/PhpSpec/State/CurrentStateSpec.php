<?php

namespace spec\PhpSpec\State;

use PhpSpec\Message\Example;
use PhpSpec\Message\Spec;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentStateSpec extends ObjectBehavior {
    function it_is_initializable() {
        $this->shouldHaveType('PhpSpec\State\CurrentState');
    }

    function it_should_return_the_current_example() {
        $this->currentExample();
    }

    function it_should_return_the_current_spec() {
        $this->currentSpec();
    }

    function it_should_update_current_spec_and_example() {
        $example = new Example();
        $spec = new Spec();

        $this->updateCurrentState($example, $spec);
    }
}
