<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;
use PhpSpec\Util\NameChecker;

class ClassNameCheckerSpec extends ObjectBehavior
{
    function it_should_be_name_checker()
    {
        $this->shouldHaveType(NameChecker::class);
    }

    function it_treats_normal_class_name_as_valid()
    {
        $this->isNameValid('Acme\Foo\Markdown')->shouldReturn(true);
    }

    function it_treats_class_name_with_reserved_keyword_chunk_in_the_middle_as_invalid()
    {
        $this->isNameValid('Acme\Namespace\Markdown')->shouldReturn(false);
    }

    function it_treats_class_name_with_reserved_keyword_chunk_on_the_left_side_as_invalid()
    {
        $this->isNameValid('Acme\Foo\List')->shouldReturn(false);
    }

    function it_treats_class_name_without_namespace_using_reserved_keywords_as_invalid()
    {
        $this->isNameValid('while')->shouldReturn(false);
    }

    function it_detects_invalid_class_name_in_any_letter_case()
    {
        $this->isNameValid('WHILE')->shouldReturn(false);
    }

}
