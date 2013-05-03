<?php

namespace PhpSpec\Event;

use PhpSpec\Loader\Suite;
use Symfony\Component\EventDispatcher\Event;

class SuiteEvent extends Event implements EventInterface
{
    private $time;

    public function __construct(Suite $suite, $time = null, $result = null)
    {
        $this->suite  = $suite;
        $this->time   = $time;
        $this->result = $result;
    }

    public function getSuite()
    {
        return $this->suite;
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getResult()
    {
        return $this->result;
    }
}
