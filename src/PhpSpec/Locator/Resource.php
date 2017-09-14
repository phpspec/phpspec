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

interface Resource
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return string
     */
    public function getSpecName(): string;

    /**
     * @return string
     */
    public function getSrcFilename(): string;

    /**
     * @return string
     */
    public function getSrcNamespace(): string;

    /**
     * @return string
     */
    public function getSrcClassname(): string;

    /**
     * @return string
     */
    public function getSpecFilename(): string;

    /**
     * @return string
     */
    public function getSpecNamespace(): string;

    /**
     * @return string
     */
    public function getSpecClassname(): string;
}
