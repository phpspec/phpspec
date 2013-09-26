<?php

namespace PhpSpec\IO;

interface IOInterface
{
    public function write($message);
    public function isVerbose();
}
