<?php

namespace spec\PhpSpec\CodeAnalysis;

use PhpSpec\ObjectBehavior;

class TokenizedNamespaceResolverSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\CodeAnalysis\NamespaceResolver');
    }

    function it_resolves_types_outside_of_namespaces()
    {
        $this->analyse('
        <?php

        class Foo
        {
        }
        ');

        $this->resolve('Bar')->shouldReturn('Bar');
        $this->resolve('Bar')->shouldReturn('Bar');
    }

    function it_resolves_types_from_current_namespace()
    {
        $this->analyse('
        <?php

        namespace Baz;

        class Foo
        {
        }

        ');

        $this->resolve('Foo')->shouldReturn('Baz\Foo');
        $this->resolve('Bar')->shouldReturn('Baz\Bar');
    }

    function it_resolves_types_with_use_statements()
    {
        $this->analyse('
        <?php

        namespace Baz;

        use Boz\Bar;

        class Foo
        {
        }

        ');

        $this->resolve('Foo')->shouldReturn('Baz\Foo');
        $this->resolve('Bar')->shouldReturn('Boz\Bar');
    }

    function it_resolves_types_with_use_aliases()
    {
        $this->analyse('
        <?php

        namespace Baz;

        use Boz\Bar as Biz;

        class Foo
        {
        }

        ');

        $this->resolve('Foo')->shouldReturn('Baz\Foo');
        $this->resolve('Biz')->shouldReturn('Boz\Bar');
    }

    function it_resolves_types_with_partial_use_statements()
    {
        $this->analyse('
        <?php

        namespace Baz;

        use Boz\Bar;

        class Foo
        {
            function it_something(Bar\Baz $boz)
            {
            }
        }

        ');

        $this->resolve('Foo')->shouldReturn('Baz\Foo');
        $this->resolve('Bar\Baz')->shouldReturn('Boz\Bar\Baz');
    }


    function it_resolves_types_from_grouped_use_statements()
    {
        $this->analyse('
        <?php

        namespace Baz;

        use Boz\{Fiz, Buz};

        class Foo
        {
            function it_something(Fiz $fiz, Buz $buz)
            {
            }
        }

        ');

        $this->resolve('Fiz')->shouldReturn('Boz\Fiz');
        $this->resolve('Buz')->shouldReturn('Boz\Buz');
    }
}
