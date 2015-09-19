<?php

namespace spec\PhpSpec\Loader\Transformer;

use PhpSpec\Loader\Transformer\TypeHintIndex;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TypeHintRewriterSpec extends ObjectBehavior
{
    function let(TypeHintIndex $typeHintIndex)
    {
        $this->beConstructedWith($typeHintIndex);
    }

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

    function it_indexes_typehints_that_are_removed(TypeHintIndex $typeHintIndex)
    {
        $this->transform('
        <?php

        class Foo
        {
            public function bar(Foo\Bar $bar, Baz $baz)
            {
            }
        }

        ');

        $typeHintIndex->add('Foo', '$bar', 'Foo\Bar')->shouldHaveBeenCalled();
        $typeHintIndex->add('Foo', '$baz', 'Baz')->shouldHaveBeenCalled();
    }

    function it_indexes_typehints_in_the_correct_namespace(TypeHintIndex $typeHintIndex)
    {
        $this->transform('
        <?php

        namespace Baz;

        class Foo
        {
            public function bar(Bar $bar)
            {
            }
        }

        ');

        $typeHintIndex->add('Baz\Foo', '$bar', 'Baz\Bar')->shouldHaveBeenCalled();
    }

    function it_indexes_typehints_that_have_applicable_use_statements(TypeHintIndex $typeHintIndex)
    {
        $this->transform('
        <?php

        namespace Baz;

        use Boz\\Bar as Bur;
        use Boz\\Bez;

        class Foo
        {
            public function bar(Bur $bar, Bez $bez)
            {
            }
        }

        ');

        $typeHintIndex->add('Baz\Foo', '$bar', 'Boz\Bar')->shouldHaveBeenCalled();
        $typeHintIndex->add('Baz\Foo', '$bez', 'Boz\Bez')->shouldHaveBeenCalled();
    }
}
