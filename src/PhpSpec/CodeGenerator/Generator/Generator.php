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

use PhpSpec\Locator\CompositeResource;

/**
 * Interface that all Generators need to implement in PhpSpec
 */
interface Generator
{
    /**
     * @param CompositeResource $resource
     * @param string            $generation
     * @param array             $data
     *
     * @return bool
     */
    public function supports(CompositeResource $resource, $generation, array $data);

    /**
     * @param CompositeResource $resource
     * @param array             $data
     */
    public function generate(CompositeResource $resource, array $data);

    /**
     * @return int
     */
    public function getPriority();
}
