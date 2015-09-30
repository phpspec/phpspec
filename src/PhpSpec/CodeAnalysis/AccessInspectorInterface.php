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

interface AccessInspectorInterface
{
    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyReadable($object, $property);

    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyWritable($object, $property);

    /**
     * @param object $object
     * @param string $method
     *
     * @return bool
     */
    public function isMethodCallable($object, $method);
}
