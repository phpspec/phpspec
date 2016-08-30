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

namespace PhpSpec\Formatter\Presenter\Value;

use PhpSpec\Exception\ErrorException;

final class BaseExceptionTypePresenter implements ExceptionTypePresenter
{
    /**
     * @param mixed $value
     * @return bool
     */
    public function supports($value)
    {
        return $value instanceof \Exception;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function present($value)
    {
        $label = 'exc';

        if ($value instanceof ErrorException) {
            $value = $value->getPrevious();
            $label = 'err';
        }

        return sprintf(
            '[%s:%s("%s")]',
            $label,
            get_class($value),
            $value->getMessage()
        );
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 60;
    }
}
