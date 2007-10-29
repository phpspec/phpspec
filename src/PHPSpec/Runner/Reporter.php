<?php

abstract class PHPSpec_Runner_Reporter
{

    protected $_result;

    public function __construct(PHPSpec_Runner_Result $result)
    {
        $this->_result = $result;
    }

    abstract public function toString();

    abstract public function __toString();

}