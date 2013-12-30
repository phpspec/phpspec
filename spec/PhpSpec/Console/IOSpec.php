<?php

namespace spec\PhpSpec\Console;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Helper\HelperSet;

class IOSpec extends ObjectBehavior
{
    function let(InputInterface $input, OutputInterface $output, HelperSet $helpers)
    {
        $this->beConstructedWith($input, $output, $helpers);
    }

    function it_has_io_interface()
    {
        $this->shouldHaveType('PhpSpec\IO\IOInterface');
    }

    function it_is_not_code_generation_ready_if_no_input_options_say_otherwise($input)
    {
        $input->getOption('no-code-generation')->willReturn(false);
        $input->isInteractive()->willReturn(true);

        $this->isCodeGenerationEnabled()->shouldReturn(true);
    }

    function it_is_not_code_generation_ready_if_input_is_not_interactive($input)
    {
        $input->getOption('no-code-generation')->willReturn(false);
        $input->isInteractive()->willReturn(false);

        $this->isCodeGenerationEnabled()->shouldReturn(false);
    }

    function it_is_not_code_generation_ready_if_command_line_option_is_set($input)
    {
        $input->getOption('no-code-generation')->willReturn(true);
        $input->isInteractive()->willReturn(true);

        $this->isCodeGenerationEnabled()->shouldReturn(false);
    }
}
