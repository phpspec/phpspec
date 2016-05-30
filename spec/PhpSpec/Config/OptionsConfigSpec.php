<?php

namespace spec\PhpSpec\Config;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;

class OptionsConfigSpec extends ObjectBehavior
{
    function it_says_rerun_is_enabled_when_setting_is_true(InputInterface $input)
    {
        $this->beConstructedWith(['rerun' => true], $input);
        $input->hasOption('no-rerun')->willReturn(false);

        $this->isReRunEnabled()->shouldReturn(true);
    }

    function it_says_rerun_is_not_enabled_when_setting_is_false(InputInterface $input)
    {
        $this->beConstructedWith(['rerun' => false], $input);

        $this->isReRunEnabled()->shouldReturn(false);
    }

    function it_says_faking_is_enabled_when_setting_is_true(InputInterface $input)
    {
        $this->beConstructedWith(['fake' => true], $input);

        $this->isFakingEnabled()->shouldReturn(true);
    }

    function it_says_faking_is_not_enabled_when_setting_is_false(InputInterface $input)
    {
        $this->beConstructedWith(['fake' => false], $input);

        $this->isFakingEnabled()->shouldReturn(false);
    }

    function it_says_bootstrap_path_is_false_when_setting_is_false(InputInterface $input)
    {
        $this->beConstructedWith(['bootstrap' => false], $input);

        $this->getBootstrapPath()->shouldReturn(false);
    }

    function it_returns_bootstrap_path_when_one_is_specified(InputInterface $input)
    {
        $this->beConstructedWith(['bootstrap' => '/path/to/file'], $input);

        $this->getBootstrapPath()->shouldReturn('/path/to/file');
    }
}
