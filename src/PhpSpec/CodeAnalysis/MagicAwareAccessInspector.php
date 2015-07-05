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

final class MagicAwareAccessInspector implements AccessInspectorInterface
{
    /**
     * @var AccessInspectorInterface
     */
    private $accessInspector;

    /**
     * @param AccessInspectorInterface $accessInspector
     */
    public function __construct(AccessInspectorInterface $accessInspector)
    {
        $this->accessInspector = $accessInspector;
    }

    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyReadable($object, $property)
    {
        return method_exists($object, '__get') || $this->accessInspector->isPropertyReadable($object, $property);
    }

    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyWritable($object, $property)
    {
        return method_exists($object, '__set') || $this->accessInspector->isPropertyWritable($object, $property);
    }

    /**
     * @param object $object
     * @param string $method
     *
     * @return bool
     */
    public function isMethodCallable($object, $method)
    {
        return method_exists($object, '__call') || $this->accessInspector->isMethodCallable($object, $method);
    }
}
