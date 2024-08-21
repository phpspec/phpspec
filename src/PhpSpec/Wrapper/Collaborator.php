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

namespace PhpSpec\Wrapper;

use Prophecy\Prophecy\ObjectProphecy;

final class Collaborator implements ObjectWrapper
{
    public function __construct(
        private ObjectProphecy $prophecy)
    {
    }

    /** @param class-string $classOrInterface */
    public function beADoubleOf(string $classOrInterface): void
    {
        if (interface_exists($classOrInterface)) {
            /** @var interface-string $classOrInterface */
            $this->prophecy->willImplement($classOrInterface);
        } else {
            /** @var class-string $classOrInterface */
            $this->prophecy->willExtend($classOrInterface);
        }
    }

    public function beConstructedWith(?array $arguments = null): void
    {
        $this->prophecy->willBeConstructedWith($arguments);
    }

    /** @param interface-string $interface */
    public function implement(string $interface): void
    {
        $this->prophecy->willImplement($interface);
    }

    public function __call(string $method, array $arguments)
    {
        return \call_user_func_array(array($this->prophecy, '__call'), array($method, $arguments));
    }

    public function __set(string $parameter, mixed $value)
    {
        $this->prophecy->$parameter = $value;
    }

    public function __get(string $parameter)
    {
        return $this->prophecy->$parameter;
    }

    public function getWrappedObject() : object
    {
        return $this->prophecy->reveal();
    }
}
