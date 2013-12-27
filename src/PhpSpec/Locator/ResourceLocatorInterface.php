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

/**
 * Interface ResourceLocatorInterface
 * @package PhpSpec\Locator
 */
interface ResourceLocatorInterface
{
    /**
     * @return mixed
     */
    public function getAllResources();

    /**
     * @param $query
     * @return mixed
     */
    public function supportsQuery($query);

    /**
     * @param $query
     * @return mixed
     */
    public function findResources($query);

    /**
     * @param $classname
     * @return mixed
     */
    public function supportsClass($classname);

    /**
     * @param $classname
     * @return mixed
     */
    public function createResource($classname);

    /**
     * @return mixed
     */
    public function getPriority();
}
