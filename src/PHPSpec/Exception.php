<?php

class PHPSpec_Exception extends Exception
{

    public function __construct($message = null, $code = 0, $file = null, $line = null, $backtrace = null)
    {
        parent::__construct($message, $code);
        if (!is_null($file)) {
            $this->file = $file;
        }
        if (!is_null($line)) {
            $this->line = $line;
        }
        if (!is_null($backtrace)) {
            $this->trace = $backtrace;
        }
    }

}