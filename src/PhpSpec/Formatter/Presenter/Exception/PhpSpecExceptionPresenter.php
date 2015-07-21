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

namespace PhpSpec\Formatter\Presenter\Exception;


use PhpSpec\Exception\Exception;

interface PhpSpecExceptionPresenter
{
    /**
     * @param Exception $exception
     * @return string
     */
    public function presentException(Exception $exception);
}
