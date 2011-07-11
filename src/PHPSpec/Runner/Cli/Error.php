<?php

namespace PHPSpec\Runner\Cli;

class Error extends \PHPSpec\Runner\Error
{
    public function __construct($message = '')
    {
        $this->message = 'phpspec: ' . $message;
    }
}