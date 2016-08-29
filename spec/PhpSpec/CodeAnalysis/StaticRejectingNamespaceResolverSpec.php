<?php

namespace spec\PhpSpec\CodeAnalysis;

use PhpSpec\CodeAnalysis\DisallowedNonObjectTypehintException;
use PhpSpec\CodeAnalysis\NamespaceResolver;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class StaticRejectingNamespaceResolverSpec extends ObjectBehavior
{
    function let(NamespaceResolver $namespaceResolver)
    {
        $this->beConstructedWith($namespaceResolver);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\CodeAnalysis\NamespaceResolver');
    }

    function it_delegates_analysis_to_wrapped_resolver(NamespaceResolver $namespaceResolver)
    {
        $this->analyse('foo');

        $namespaceResolver->analyse('foo')->shouldhaveBeenCalled();
    }

    function it_delegates_resolution_to_wrapped_resolver(NamespaceResolver $namespaceResolver)
    {
        $namespaceResolver->resolve('Bar')->willReturn('Foo\Bar');

        $this->resolve('Bar')->shouldReturn('Foo\Bar');
    }

    function it_does_not_allow_resolution_of_non_object_types()
    {
        $this->shouldThrow(DisallowedNonObjectTypehintException::class)->duringResolve('int');
        $this->shouldThrow(DisallowedNonObjectTypehintException::class)->duringResolve('float');
        $this->shouldThrow(DisallowedNonObjectTypehintException::class)->duringResolve('string');
        $this->shouldThrow(DisallowedNonObjectTypehintException::class)->duringResolve('bool');
        $this->shouldThrow(DisallowedNonObjectTypehintException::class)->duringResolve('iterable');
    }
}
