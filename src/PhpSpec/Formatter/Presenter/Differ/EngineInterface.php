<?php

namespace PhpSpec\Formatter\Presenter\Differ;

interface EngineInterface
{
    public function supports($expected, $actual);
    public function compare($expected, $actual);
}
