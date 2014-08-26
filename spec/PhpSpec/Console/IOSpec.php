<?php

namespace spec\PhpSpec\Console;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\ServiceContainer;
use PhpSpec\Config\OptionsConfig;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IOSpec extends ObjectBehavior
{
    function let(InputInterface $input, OutputInterface $output, DialogHelper $dialogHelper, OptionsConfig $config)
    {
        $input->isInteractive()->willReturn(true);
        $input->getOption('no-code-generation')->willReturn(false);
        $input->getOption('stop-on-failure')->willReturn(false);

        $config->isCodeGenerationEnabled()->willReturn(true);
        $config->isStopOnFailureEnabled()->willReturn(false);

        $this->beConstructedWith($input, $output, $dialogHelper, $config);
    }

    function it_has_io_interface()
    {
        $this->shouldHaveType('PhpSpec\IO\IOInterface');
    }

    function it_is_code_generation_ready_if_no_input_config_say_otherwise()
    {
        $this->isCodeGenerationEnabled()->shouldReturn(true);
    }

    function it_is_not_code_generation_ready_if_input_is_not_interactive($input)
    {
        $input->isInteractive()->willReturn(false);

        $this->isCodeGenerationEnabled()->shouldReturn(false);
    }

    function it_is_not_code_generation_ready_if_command_line_option_is_set($input)
    {
        $input->getOption('no-code-generation')->willReturn(true);

        $this->isCodeGenerationEnabled()->shouldReturn(false);
    }

    function it_is_not_code_generation_ready_if_config_option_is_set($config)
    {
        $config->isCodeGenerationEnabled()->willReturn(false);

        $this->isCodeGenerationEnabled()->shouldReturn(false);
    }

    function it_will_not_stop_on_failure_if_no_input_config_say_otherwise()
    {
        $this->isStopOnFailureEnabled()->shouldReturn(false);
    }

    function it_will_stop_on_failure_if_if_command_line_option_is_set($input)
    {
        $input->getOption('stop-on-failure')->willReturn(true);

        $this->isStopOnFailureEnabled()->shouldReturn(true);
    }

    function it_will_stop_on_failure_if_config_option_is_set($config)
    {
        $config->isStopOnFailureEnabled()->willReturn(true);

        $this->isStopOnFailureEnabled()->shouldReturn(true);
    }

    function it_will_enable_rerunning_if_command_line_option_is_not_set($input)
    {
        $input->getOption('no-rerun')->willReturn(false);

        $this->isRerunEnabled()->shouldReturn(true);
    }

    function it_will_disable_rerunning_if_command_line_option_is_set($input)
    {
        $input->getOption('no-rerun')->willReturn(true);

        $this->isRerunEnabled()->shouldReturn(false);
    }
}
