<?php

namespace PHPSpec\Specification;

use \PHPSpec\Exception,
    \PHPSpec\Util\Backtrace;

abstract class Result extends Exception
{
    public function getSnippet($index = 0)
    {
        return Backtrace::code($this->getTrace(), $index);
    }
    
    public function prettyTrace($limit = 3)
    {
        return Backtrace::pretty($this->getTrace(), $limit);
    }
    
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!($errno & error_reporting())) {
            return;
        }

        $backtrace = debug_backtrace();
        array_shift($backtrace);

        throw new Result\Error(
            $errstr, $errno, $errfile, $errline, $backtrace
        );

        return true;
    }
}