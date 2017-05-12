<?php

namespace spec\PhpSpec\NamespaceProvider;

use PhpSpec\NamespaceProvider\ComposerPsrNamespaceProvider;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ComposerPsrNamespaceProviderSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(__DIR__ . '/../../..', 'spec');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ComposerPsrNamespaceProvider::class);
    }

    public function it_should_return_a_map_of_locations()
    {
        $this->getNamespaces()->shouldReturn(array('PhpSpec' => 'src'));
    }
}
