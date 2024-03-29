<?php

namespace spec\PhpSpec\Config;

use PhpSpec\ObjectBehavior;
use Symfony\Component\Console\Output\OutputInterface;

class OptionsConfigSpec extends ObjectBehavior
{
    function it_says_rerun_is_enabled_when_setting_is_true()
    {
        $this->beConstructedWith(false, false, true, false, false, false);

        $this->isReRunEnabled()->shouldReturn(true);
    }

    function it_says_rerun_is_not_enabled_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false, false, false, false);

        $this->isReRunEnabled()->shouldReturn(false);
    }

    function it_says_faking_is_enabled_when_setting_is_true()
    {
        $this->beConstructedWith(false, false, false, true, false, false);

        $this->isFakingEnabled()->shouldReturn(true);
    }

    function it_says_faking_is_not_enabled_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false, false, false, false);

        $this->isFakingEnabled()->shouldReturn(false);
    }

    function it_says_bootstrap_path_is_false_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false, false, false, false);

        $this->getBootstrapPath()->shouldReturn(null);
    }

    function it_returns_bootstrap_path_when_one_is_specified()
    {
        $this->beConstructedWith(false, false, false, false, '/path/to/file', false);

        $this->getBootstrapPath()->shouldReturn('/path/to/file');
    }

    function it_returns_verbose_when_setting_is_true()
    {
        $this->beConstructedWith(false, false, false, false, false, true);

        $this->isVerbose()->shouldReturn(true);
    }

    function it_returns_verbose_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false, false, false, false);

        $this->isVerbose()->shouldReturn(false);
    }
}
