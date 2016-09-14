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

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Locator\Resource;

final class ImplementsGenerator implements Generator
{
    /**
     * @param Resource $resource
     * @param string   $generation
     * @param array    $data
     *
     * @return bool
     */
    public function supports(Resource $resource, $generation, array $data)
    {
        return 'implements' === $generation;
    }

    /**
     * @param Resource $resource
     * @param array    $data
     */
    public function generate(Resource $resource, array $data)
    {
        // TODO: Implement generate() method.
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 0;
    }
}
