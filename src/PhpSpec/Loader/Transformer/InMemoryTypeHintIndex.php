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

namespace PhpSpec\Loader\Transformer;

final class InMemoryTypeHintIndex implements TypeHintIndex
{
    /**
     * @var array
     */
    private $typehints = array();

    /**
     * @param string $class
     * @param string $argument
     * @param string $typehint
     */
    public function add($class, $argument, $typehint)
    {
        if (!array_key_exists($class, $this->typehints)) {
            $this->typehints[$class] = array();
        }

        $this->typehints[$class][$argument] = $typehint;
    }

    /**
     * @param string $class
     * @param string $argument
     *
     * @return string|null
     */
    public function lookup($class, $argument)
    {
        if (isset($this->typehints[$class][$argument])) {
            return $this->typehints[$class][$argument];
        }

        return false;
    }
}
