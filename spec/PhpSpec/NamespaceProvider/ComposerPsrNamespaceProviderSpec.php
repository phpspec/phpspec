<?php

namespace spec\PhpSpec\NamespaceProvider;

use PhpSpec\NamespaceProvider\ComposerPsrNamespaceProvider;
use PhpSpec\NamespaceProvider\NamespaceLocation;
use PhpSpec\NamespaceProvider\NamespaceProvider;
use PhpSpec\ObjectBehavior;

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
        $this->getNamespaces()->shouldHaveKey('PhpSpec');
        $this->getNamespaces()->shouldHaveNamespaceLocation(
            'PhpSpec',
            'src',
            NamespaceProvider::AUTOLOADING_STANDARD_PSR0
        );
    }

    public function getMatchers(): array
    {
        return array(
            'haveNamespaceLocation' => function ($subject, $namespace, $location, $standard) {
                $expectedNamespaceLocation = new NamespaceLocation(
                    $namespace,
                    $location,
                    $standard
                );
                foreach ($subject as $namespaceLocation) {
                    if ($namespaceLocation == $expectedNamespaceLocation) {
                        return true;
                    }
                }

                return false;
            }
        );
    }
}
