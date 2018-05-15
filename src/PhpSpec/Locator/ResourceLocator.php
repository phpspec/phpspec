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

    /**
     * @param string $query
     *
     * @return boolean
     */
    public function supportsQuery(string $query): bool;

    /**
     * @param string $query
     *
     * @return Resource[]
     */
    public function findResources(string $query);

    /**
     * @param string $classname
     *
     * @return boolean
     */
    public function supportsClass(string $classname): bool;

    /**
     * @param string $classname
     *
     * @return Resource|null
     */
    public function createResource(string $classname);

    /**
     * @return integer
     */
    public function getPriority(): int;
}
