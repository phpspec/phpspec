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

namespace PhpSpec\Locator;

interface ResourceLocator
{
    /**
     * @return Resource[]
     */
    public function getAllResources(): array;

    public function supportsQuery(string $query): bool;

    /**
     * @return Resource[]
     */
    public function findResources(string $query): array;

    public function supportsClass(string $classname): bool;

    public function createResource(string $classname): ?Resource;

    public function getPriority(): int;
}
