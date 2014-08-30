<?php

namespace Console;

use PhpSpec\Process\ReRunner;

class LoggingReRunner implements ReRunner
{
    private $hasBeenReRun = false;

    /**
     * @return boolean
     */
    public function isSupported()
    {
        return true;
    }

    public function reRunSuite()
    {
        $this->hasBeenReRun = true;
    }

    public function hasBeenReRun()
    {
        return $this->hasBeenReRun;
    }
}
