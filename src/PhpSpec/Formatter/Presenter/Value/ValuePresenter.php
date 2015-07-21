<?php
/**
 * Created by PhpStorm.
 * User: shane
 * Date: 21/07/15
 * Time: 08:44
 */

namespace PhpSpec\Formatter\Presenter\Value;


interface ValuePresenter
{
    /**
     * @param mixed $value
     * @return string
     */
    public function presentValue($value);
}
