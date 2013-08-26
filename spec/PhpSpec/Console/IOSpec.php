<?php

namespace spec\PhpSpec\Console;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Helper\HelperSet,
    Symfony\Component\Console\Helper\DialogHelper;

class IOSpec extends ObjectBehavior
{
    function let(InputInterface $input, OutputInterface $output, 
                 HelperSet $helpers, DialogHelper $dialogHelper)
    {
        $helpers->get('dialog')->willReturn($dialogHelper);
        
        $this->beConstructedWith($input, $output, $helpers);
    }
    
    function it_prompts_for_confirmation_when_input_is_interactive($input, $output, $dialogHelper)
    {
        $input->isInteractive()->willReturn(true);
        
        $this->askConfirmation('QUESTION', 'DEFAULT');
        $dialogHelper->askConfirmation($output, Argument::any(), 'DEFAULT')->shouldHaveBeenCalled();
    }
    
    function it_returns_default_without_prompting_when_input_is_not_interactive($input, $dialogHelper)
    {
        $input->isInteractive()->willReturn(false);
        
        $this->askConfirmation('Well?', 'DEFAULT')->shouldReturn('DEFAULT');
        $dialogHelper->askConfirmation(Argument::any(), Argument::any(), Argument::any())->shouldNotBeenCalled();
    }
}
