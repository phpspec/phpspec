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

namespace PhpSpec;

use Prophecy\Exception\InvalidArgumentException;

class ServiceFactory
{
    private $services = array();
    private $container;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function setService($id, $service)
    {
        $this->services[$id] = $service;
    }

    public function create($id)
    {
        if (!isset($this->services[$id])) {
            throw new InvalidArgumentException(sprintf('Legacy service "%s" has not been registered successfully.', $id));
        }

        $service = $this->services[$id];

        if (is_callable($service)) {
            return $this->services[$id]($this->container);
        }

        return $this->services[$id];
    }
}
