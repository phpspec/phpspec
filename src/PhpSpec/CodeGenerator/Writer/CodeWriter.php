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

namespace PhpSpec\CodeGenerator\Writer;

interface CodeWriter
{
    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function insertMethodFirstInClass($class, $method);

    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function insertMethodLastInClass($class, $method);

    /**
     * @param string $class
     * @param string $methodName
     * @param string $method
     * @return string
     */
    public function insertAfterMethod($class, $methodName, $method);
}
