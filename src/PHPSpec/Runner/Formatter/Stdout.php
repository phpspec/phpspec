<?php

namespace PHPSpec\Runner\Formatter;

class Stdout extends \SplFileObject
{
    public function __construct($filename)
    {
        parent::__construct('php://stdout', 'w');
    }
    
    public static function put($stream)
    {
        $stdout = new self('php://stdout');
        $stdout->fwrite($stream);
    }
}