<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\CodeAnalysis;

final class StaticRejectingNamespaceResolver implements NamespaceResolver
{
    public function __construct(
        private NamespaceResolver $namespaceResolver
    )
    {
    }

    public function analyse(string $code): void
    {
        $this->namespaceResolver->analyse($code);
    }

    public function resolve(string $typeAlias): string
    {
        $this->guardNonObjectTypeHints($typeAlias);

        return $this->namespaceResolver->resolve($typeAlias);
    }

    /**
     * @throws \Exception
     */
    private function guardNonObjectTypeHints(string $typeAlias) : void
    {
        $nonObjectTypes = [
            'int',
            'float',
            'string',
            'bool',
            'iterable',
        ];

        if (\in_array($typeAlias, $nonObjectTypes, true)) {
            throw new DisallowedNonObjectTypehintException("Non-object type $typeAlias cannot be resolved within a namespace");
        }
    }
}
