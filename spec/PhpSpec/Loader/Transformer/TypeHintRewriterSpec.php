<?php

namespace spec\PhpSpec\Loader\Transformer;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TypeHintRewriterSpec extends ObjectBehavior
{
    function it_is_a_transformer()
    {
        $this->shouldHaveType('PhpSpec\Loader\SpecTransformer');
    }

    function it_leaves_alone_specs_with_no_typehints()
    {
        $this->transform('
        <?php

        class Foo
        {
            public function bar()
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function bar()
            {
            }
        }

        ');
    }

    function it_removes_typehints_from_single_argument_methods()
    {
        $this->transform('
        <?php

        class Foo
        {
            public function bar(\Foo\Bar $bar)
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function bar($bar)
            {
            }
        }

        ');
    }

    function it_removes_typehints_for_multiple_arguments_in_methods()
    {
        $this->transform('
        <?php

        class Foo
        {
            public function bar(Bar $bar, Baz $baz)
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function bar($bar,$baz)
            {
            }
        }

        ');
    }
}
