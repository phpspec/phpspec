<?php

namespace spec\PhpSpec\Console\Provider;

use PhpSpec\Console\Provider\NamespacesAutocompleteProvider;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class NamespacesAutocompleteProviderSpec extends ObjectBehavior
{
    function let(Finder $finder)
    {
        $this->beConstructedWith($finder);
    }

    function it_returns_empty_array_if_nothing_found($finder)
    {
        $finder->files()->willReturn($finder);
        $finder->name('*.php')->willReturn($finder);
        $finder->in(['/app/src'])->willReturn([]);

        $namespaces = $this->getNamespaces(['/app/src'])->shouldHaveCount(0);
    }

    function it_returns_namespaces_from_php_files(
        $finder,
        SplFileInfo $file1,
        SplFileInfo $file2,
        SplFileInfo $file3
    ) {
        $finder->files()->shouldBeCalled()->willReturn($finder);
        $finder->name('*.php')->shouldBeCalled()->willReturn($finder);
        $finder->in(['/app/src'])->shouldBeCalled()->willReturn([$file1, $file2, $file3]);

        $file1->getContents()->willReturn('<?php namespace App\Foo; class Foo {}');
        $file2->getContents()->willReturn('<?php namespace App\Foo; class Bar {}');
        $file3->getContents()->willReturn('<?php namespace App\Bar; class Foo {}');

        $namespaces = $this->getNamespaces(['/app/src']);

        $namespaces->shouldHaveCount(3);
        $namespaces->shouldContain('App');
        $namespaces->shouldContain('App\Foo');
        $namespaces->shouldContain('App\Bar');
    }
}
