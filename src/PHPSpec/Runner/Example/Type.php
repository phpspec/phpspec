<?php

class PHPSpec_Runner_Example_Type
{

    protected $_example = null;
    protected $_isPass = false;
    protected $_isFail = false;
    protected $_isException = false;
    protected $_isError = false;

    public function __construct(PHPSpec_Runner_Example $example)
    {
        $this->_example = $example;
    }

    public function getExample()
    {
        return $this->_example;
    }

    public function isPass()
    {
        return $this->_isPass;
    }

    public function isFail()
    {
        return $this->_isFail;
    }

    public function isException()
    {
        return $this->_isException;
    }

    public function isError()
    {
        return $this->_isError;
    }

    public function __call($method, $args)
    {
        if (method_exists($this->_example, $method)) {
            return call_user_func_array(array($this->_example, $method), $args);
        }
    }

}