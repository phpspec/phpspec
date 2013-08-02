<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Formatter\Presenter\StringPresenter;
use Exception;

class HtmlPresenter extends StringPresenter
{
    public function presentException(Exception $exception, $verbose = false)
    {
        return parent::presentException($exception, $verbose);
    }
}
