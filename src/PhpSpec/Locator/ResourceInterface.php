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

/**
 * Interface ResourceInterface
 * @package PhpSpec\Locator
 */
interface ResourceInterface
{
    /**
     * @return mixed
     */
    public function getName();

    /**
     * @return mixed
     */
    public function getSpecName();

    /**
     * @return mixed
     */
    public function getSrcFilename();

    /**
     * @return mixed
     */
    public function getSrcNamespace();

    /**
     * @return mixed
     */
    public function getSrcClassname();

    /**
     * @return mixed
     */
    public function getSpecFilename();

    /**
     * @return mixed
     */
    public function getSpecNamespace();

    /**
     * @return mixed
     */
    public function getSpecClassname();
}
