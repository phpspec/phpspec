<?php

class PHPSpec_Runner_Example_Exception extends PHPSpec_Runner_Example_Type
{

    protected $_isException = true;

    protected $_exception = null;

    public function __construct(PHPSpec_Runner_Example $example, Exception $e)
    {
        parent::__construct($example);
        $this->_exception = $e;
    }

    public function getException()
    {
        return $this->_exception;
    }

    public function __call($method, $args)
    {
        if (method_exists($this->_example, $method)) {
            return call_user_func_array(array($this->_example, $method), $args);
        }
        if (method_exists($this->_exception, $method)) {
            return call_user_func_array(array($this->_exception, $method), $args);
        }
    }

}