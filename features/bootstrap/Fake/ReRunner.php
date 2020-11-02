<?php

namespace Fake;

use PhpSpec\Process\ReRunner as BaseReRunner;

class ReRunner implements BaseReRunner
{
    private $hasBeenReRun = false;

    public function isSupported(): bool
    {
        return true;
    }

    public function reRunSuite() : void
    {
        $this->hasBeenReRun = true;
    }

    public function hasBeenReRun()
    {
        return $this->hasBeenReRun;
    }
}
