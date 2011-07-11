<?php

namespace PHPSpec\Specification\Result;

use \PHPSpec\Specification\Result;

class Exception extends Result
{
    protected $_exceptionClass;
    
    public function __construct($exception = '')
    {
        $this->_exceptionClass = get_class($exception);
        parent::__construct($exception->getMessage());
    }
    
    public function getExceptionClass()
    {
        return $this->_exceptionClass;
    }
}