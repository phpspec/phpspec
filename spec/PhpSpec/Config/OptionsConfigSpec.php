<?php

namespace spec\PhpSpec\Config;

use PhpSpec\ObjectBehavior;

class OptionsConfigSpec extends ObjectBehavior
{
    function it_says_rerun_is_enabled_when_setting_is_true()
    {
        $this->beConstructedWith(false, false, true, false);

        $this->isReRunEnabled()->shouldReturn(true);
    }

    function it_says_rerun_is_not_enabled_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false, false);

        $this->isReRunEnabled()->shouldReturn(false);
    }

    function it_says_faking_is_enabled_when_setting_is_true()
    {
        $this->beConstructedWith(false, false, false, true);

        $this->isFakingEnabled()->shouldReturn(true);
    }

    function it_says_faking_is_not_enabled_when_setting_is_false()
    {
        $this->beConstructedWith(false, false, false, false);

        $this->isFakingEnabled()->shouldReturn(false);
    }
}
