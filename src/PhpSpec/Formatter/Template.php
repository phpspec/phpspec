<?php

namespace PhpSpec\Formatter;

interface Template
{
    public function render($text, array $templateVars = array());
}
