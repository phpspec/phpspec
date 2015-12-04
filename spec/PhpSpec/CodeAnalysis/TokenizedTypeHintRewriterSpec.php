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
        $this->rewrite('
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

    function it_does_not_remove_typehints_in_methods()
    {
        $this->rewrite('
        <?php

        class Foo
        {
            public function bar(\Foo\Bar $bar)
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
            public function bar($bar)
            {
                new class($argument) implements InterfaceName
                {
                    public function foo(Foo $foo) {}
                };
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
            public function bar(Foo\Bar $bar, Baz $baz)
            {
            }
        }

        ');

        $typeHintIndex->add('Foo', 'bar', '$bar', 'Foo\Bar')->shouldHaveBeenCalled();
        $typeHintIndex->add('Foo', 'bar', '$baz', 'Baz')->shouldHaveBeenCalled();
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
            public function bar(int $bar)
            {
            }
        }

        ');

        $typeHintIndex->addInvalid('Foo', 'bar', '$bar', $e)->shouldHaveBeenCalled();
        $typeHintIndex->add('Foo', 'bar', '$bar', Argument::any())->shouldNotHaveBeenCalled();
    }
}
