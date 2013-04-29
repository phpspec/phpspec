<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;

class SuiteEvent extends Event implements EventInterface
{
    private $time;

    public function __construct($time = null, $result = null)
    {
        $this->time   = $time;
        $this->result = $result;
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
