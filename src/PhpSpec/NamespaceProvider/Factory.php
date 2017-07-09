<?php

namespace PhpSpec\NamespaceProvider;

final class Factory
{
    public function getProvider($identifier, array $arguments)
    {
        switch ($identifier) {
            case 'composer':
                $arguments = array_merge(array(
                    'root_directory' => '.',
                    'spec_prefix' => 'spec',
                ), $arguments);
                return new ComposerPsrNamespaceProvider(
                    $arguments['root_directory'],
                    $arguments['spec_prefix']
                );
            default:
                throw new \LogicException(sprintf(
                    'Unknown namespace provider "%s"',
                    $identifier
                ));
        }
    }
}
