<?php

namespace spec\PhpSpec\Util\Token;

use PhpSpec\ObjectBehavior;
use PhpSpec\Util\Token;
use PhpSpec\Util\Token\ArrayToken;

class ArrayTokenSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedThrough(
            [Token::class, 'fromPhpToken'],
            [[T_CATCH, 'catch', 100]]
        );
    }

    function it_equals_the_string_in_token_array_it_was_created_with()
    {
        $this->equals('catch')->shouldBe(true);
    }

    function it_stringifys_as_the_string_in_token_array_it_was_created_with()
    {
        $this->asString()->shouldBe('catch');
    }

    function it_does_not_equal_string_different_to_one_in_token_array_it_was_created_with()
    {
        $this->equals('bar')->shouldBe(false);
    }

    function it_has_same_type_as_token_array_it_was_created_with()
    {
        $this->hasType(T_CATCH)->shouldBe(true);
    }

    function it_does_not_have_same_type_as_token_different_to_array_it_was_created_with()
    {
        $this->hasType(T_FINALLY)->shouldBe(false);
    }

    function it_is_in_a_matching_type_list()
    {
        $this->isInTypes([T_CATCH])->shouldBe(true);
    }

    function it_is_not_in_a_non_matching_type_list()
    {
        $this->isInTypes([T_FINALLY])->shouldBe(false);
    }

    function it_has_a_line()
    {
        $this->getLine()->shouldBe(100);
    }
}
