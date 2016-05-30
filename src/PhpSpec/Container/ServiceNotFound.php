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

namespace PhpSpec\Container;

use Interop\Container\Exception\NotFoundException;

class ServiceNotFound extends \InvalidArgumentException implements NotFoundException
{
    /**
     * @param string $serviceId
     * @return ServiceNotFound
     */
    public static function constructFromServiceId($serviceId)
    {
        return new ServiceNotFound('Service "' . $serviceId . '" not found in container');
    }
}
