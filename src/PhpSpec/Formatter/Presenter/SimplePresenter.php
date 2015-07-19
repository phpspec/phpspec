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

namespace PhpSpec\Formatter\Presenter;

use PhpSpec\Formatter\Presenter\Value\TypePresenter;

final class SimplePresenter implements Presenter
{
    /**
     * @var TypePresenter[]
     */
    private $typePresenters = array();

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function presentValue($value)
    {
        foreach ($this->typePresenters as $typePresenter) {
            if ($typePresenter->supports($value)) {
                return $typePresenter->present($value);
            }
        }

        return sprintf('[%s:%s]', strtolower(gettype($value)), $value);
    }

    /**
     * @param \Exception $exception
     * @param bool $verbose
     *
     * @return string
     */
    public function presentException(\Exception $exception, $verbose = false)
    {
        // TODO: Implement presentException() method.
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function presentString($string)
    {
        return $string;
    }

    /**
     * @param TypePresenter $typePresenter
     */
    public function addTypePresenter(TypePresenter $typePresenter)
    {
        $this->typePresenters[] = $typePresenter;
    }
}
