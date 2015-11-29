<?php

namespace spec\PhpSpec\CodeAnalysis;

use PhpSpec\CodeAnalysis\DisallowedScalarTypehintException;
use PhpSpec\CodeAnalysis\NamespaceResolver;
use PhpSpec\Loader\Transformer\TypeHintIndex;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TokenizedTypeHintRewriterSpec extends ObjectBehavior
{
    function let(TypeHintIndex $typeHintIndex, NamespaceResolver $namespaceResolver)
    {
        $this->beConstructedWith($typeHintIndex, $namespaceResolver);
    }

    function it_is_a_typehint_rewriter()
    {
        $this->shouldHaveType('PhpSpec\CodeAnalysis\TypeHintRewriter');
    }

    function it_leaves_alone_specs_with_no_typehints()
    {
        $this->rewrite('
        <?php

        class Foo
        {
            public function it_do_bar()
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function it_do_bar()
            {
            }
        }

        ');
    }

    function it_removes_typehints_from_single_argument_methods()
    {
        $this->rewrite('
        <?php

        class Foo
        {
            public function it_do_bar(\Foo\Bar $bar)
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function it_do_bar($bar)
            {
            }
        }

        ');
    }

    function it_removes_typehints_from_single_argument_methods_that_starts_with_its()
    {
        $this->rewrite('
        <?php

        class Foo
        {
            public function its_do_bar(\Foo\Bar $bar)
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function its_do_bar($bar)
            {
            }
        }

        ');
    }

    function it_removes_typehints_for_multiple_arguments_in_methods()
    {
        $this->rewrite('
        <?php

        class Foo
        {
            public function it_do_bar(Bar $bar, Baz $baz)
            {
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function it_do_bar($bar,$baz)
            {
            }
        }

        ');
    }

    function it_do_not_removes_type_hints_of_a_function_that_do_not_starts_with_it()
    {
        $this->rewrite('
        <?php

        class Foo
        {
            public function it_do_bar(Bar $bar, Baz $baz)
            {
                new class($argument) implements InterfaceName
                {
                    public function foo(Foo $foo) {}
                };
            }
        }

        ')->shouldReturn('
        <?php

        class Foo
        {
            public function it_do_bar($bar,$baz)
            {
                new class($argument) implements InterfaceName
                {
                    public function foo(Foo $foo) {}
                };
            }
        }

        ');
    }

    function it_indexes_typehints_that_are_removed(TypeHintIndex $typeHintIndex, NamespaceResolver $namespaceResolver)
    {
        $namespaceResolver->analyse(Argument::any())->shouldBeCalled();

        $namespaceResolver->resolve('Foo')->willReturn('Foo');
        $namespaceResolver->resolve('Foo\Bar')->willReturn('Foo\Bar');
        $namespaceResolver->resolve('Baz')->willReturn('Baz');

        $this->rewrite('
        <?php

        class Foo
        {
            public function it_do_bar(Foo\Bar $bar, Baz $baz)
            {
            }
        }

        ');

        $typeHintIndex->add('Foo', 'it_do_bar', '$bar', 'Foo\Bar')->shouldHaveBeenCalled();
        $typeHintIndex->add('Foo', 'it_do_bar', '$baz', 'Baz')->shouldHaveBeenCalled();
    }

    function it_indexes_invalid_typehints(
        TypeHintIndex $typeHintIndex,
        NamespaceResolver $namespaceResolver
    ) {
        $e = new DisallowedScalarTypehintException();
        $namespaceResolver->analyse(Argument::any())->shouldBeCalled();

        $namespaceResolver->resolve('Foo')->willReturn('Foo');
        $namespaceResolver->resolve('int')->willThrow($e);

        $this->rewrite('
        <?php

        class Foo
        {
            public function it_do_bar(int $bar)
            {
            }
        }

        ');

        $typeHintIndex->addInvalid('Foo', 'it_do_bar', '$bar', $e)->shouldHaveBeenCalled();
        $typeHintIndex->add('Foo', 'it_do_bar', '$bar', Argument::any())->shouldNotHaveBeenCalled();
    }
}
