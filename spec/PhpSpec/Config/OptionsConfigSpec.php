<?php

namespace spec\PhpSpec\Config;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class OptionsConfigSpec extends ObjectBehavior
{
    function it_says_rerun_is_enabled_when_setting_is_true()
    {
        $this->beConstructedWith(false, false, true);

        $this->isReRunEnabled()->shouldReturn(true);
    }

    function it_says_rerun_is_not_enabled_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false);

        $this->isReRunEnabled()->shouldReturn(false);
    }
}
