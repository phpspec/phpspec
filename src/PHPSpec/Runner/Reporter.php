<?php

abstract class PHPSpec_Runner_Reporter
{

    protected $_result;

    protected $_doSpecdox = false;

    public function __construct(PHPSpec_Runner_Result $result)
    {
        $this->_result = $result;
    }

    public function doSpecdox($bool = true)
    {
        $this->_doSpecdox = $bool;
    }

    abstract public function toString();

    abstract public function getSpecdox();

    abstract public function __toString();

}