<?php

namespace integration\PhpSpec\Loader;

use PhpSpec\CodeAnalysis\TokenizedNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedTypeHintRewriter;
use PhpSpec\Loader\StreamWrapper;
use PhpSpec\Loader\Transformer\InMemoryTypeHintIndex;
use PhpSpec\Loader\Transformer\TypeHintRewriter;
use PHPUnit\Framework\TestCase;

class StreamWrapperTest extends TestCase
{
    function setUp() : void
    {
        $wrapper = new StreamWrapper();
        $wrapper->addTransformer(new TypeHintRewriter(new TokenizedTypeHintRewriter(new InMemoryTypeHintIndex(), new TokenizedNamespaceResolver())));

        StreamWrapper::register();
    }

    /**
     * @test
     * @requires PHP 7.0
     */
    function it_loads_a_spec_with_no_typehints()
    {
        require StreamWrapper::wrapPath(__DIR__.'/examples/ExampleSpec.php');

        $reflection = new \ReflectionClass('integration\PhpSpec\Loader\examples\ExampleSpec');
        $method = $reflection->getMethod('it_requires_a_stdclass');
        $parameters = $method->getParameters();

        $this->assertNull($parameters[0]->getType());
    }
}
