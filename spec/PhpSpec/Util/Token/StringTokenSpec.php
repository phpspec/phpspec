<?php

namespace spec\PhpSpec\Util\Token;

use PhpSpec\ObjectBehavior;
use PhpSpec\Util\Token;
use PhpSpec\Util\Token\StringToken;

class StringTokenSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedThrough(
            [Token::class, 'fromPhpToken'],
            ['f']
        );
    }

    function it_equals_the_string_it_was_created_with()
    {
        $this->equals('f')->shouldBe(true);
    }

    function it_stringifys_as_the_string_it_was_created_with()
    {
        $this->asString()->shouldBe('f');
    }

    function it_does_not_equal_different_string()
    {
        $this->equals('b')->shouldBe(false);
    }

    function it_does_not_have_type()
    {
        $this->hasType(T_CATCH)->shouldBe(false);
    }

    function it_is_never_in_a_type_list()
    {
        $this->isInTypes([T_CATCH])->shouldBe(false);
    }
}
