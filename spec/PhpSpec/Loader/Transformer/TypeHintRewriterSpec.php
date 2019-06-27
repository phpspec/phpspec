<?php

namespace spec\PhpSpec\Loader\Transformer;

use PhpSpec\CodeAnalysis\TypeHintRewriter;
use PhpSpec\ObjectBehavior;

class TypeHintRewriterSpec extends ObjectBehavior
{
    function let(TypeHintRewriter $rewriter)
    {
        $this->beConstructedWith($rewriter);
    }

    function it_is_a_transformer()
    {
        $this->shouldHaveType('PhpSpec\Loader\SpecTransformer');
    }

    function it_delegates_transforming_to_rewriter(TypeHintRewriter $rewriter)
    {
        $rewriter->rewrite('<?php echo "hello world";')->willReturn('hello world');

        $this->transform('<?php echo "hello world";')->shouldReturn('hello world');
    }
}
