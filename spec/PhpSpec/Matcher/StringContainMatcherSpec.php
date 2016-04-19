<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class StringContainMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentString(Argument::type('string'))->willReturnArgument();

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\Matcher');
    }

    function it_supports_contain_keyword_string_subject_and_argument()
    {
        $this->supports('contain', 'hello world', array('llo'))->shouldReturn(true);
    }

    function it_does_not_support_non_string_keyword()
    {
        $this->supports('contain', array(), array())->shouldReturn(false);
    }

    function it_does_not_support_missing_argument()
    {
        $this->supports('contain', 'hello world', array())->shouldReturn(false);
    }

    function it_does_not_support_non_string_argument()
    {
        $this->supports('contain', 'hello world', array(array()))->shouldReturn(false);
    }

    function it_matches_strings_that_contain_specified_substring()
    {
        $this->shouldNotThrow()->duringPositiveMatch('contains', 'hello world', array('ello'));
    }

    function it_does_not_match_strings_that_do_not_contain_specified_substring()
    {
        $this->shouldThrow()->duringPositiveMatch('contains', 'hello world', array('row'));
    }

    function it_matches_strings_that_do_not_contain_specified_substring()
    {
        $this->shouldNotThrow()->duringNegativeMatch('contains', 'hello world', array('row'));
    }

    function it_does_not_match_strings_that_do_contain_specified_substring()
    {
        $this->shouldThrow()->duringNegativeMatch('contains', 'hello world', array('ello'));
    }
}
