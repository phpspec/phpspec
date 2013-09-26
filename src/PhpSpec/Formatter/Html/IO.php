<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\IO\IOInterface;

class IO implements IOInterface
{
    public function write($message)
    {
        echo $message;
    }

    public function isVerbose()
    {
        return true;
    }
}
