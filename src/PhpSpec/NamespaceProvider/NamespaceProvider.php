<?php

namespace PhpSpec\NamespaceProvider;

/**
 * Provides project namespaces and where to find them.
 */
interface NamespaceProvider
{
    /**
     * @return string[] a map associating a namespace to a location, e.g
     *                  ['My\Namespace' => 'my/location/relative/to/spec/or/src/directory']
     */
    public function getNamespaces();
}
