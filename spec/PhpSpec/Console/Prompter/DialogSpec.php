<?php

namespace spec\PhpSpec\Console\Prompter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\OutputInterface;

class DialogSpec extends ObjectBehavior
{
    function let(OutputInterface $output, DialogHelper $helper)
    {
        $this->beConstructedWith($output, $helper);
    }

    function it_is_a_prompter()
    {
        $this->shouldHaveType('PhpSpec\Console\Prompter');
    }

    function it_can_ask_for_confirmation_and_return_the_result(OutputInterface $output, DialogHelper $helper)
    {
        $helper->askConfirmation(Argument::cetera())->willReturn(true);

        $result = $this->askConfirmation('Are you sure?');

        $helper->askConfirmation($output, 'Are you sure?', true)->shouldHaveBeenCalled();
        $result->shouldBe(true);
    }
}
