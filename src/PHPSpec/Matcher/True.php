<?php

class PHPSpec_Matcher_True implements PHPSpec_Matcher_Interface
{

    protected $_expected = true;

    protected $_actual = null;

    public function __construct()
    {}

    public function matches($actual)
    {
        $this->_actual = $actual;
        return $this->_expected === $this->_actual;
    }

    public function getFailureMessage()
    {
        return 'expected TRUE , got FALSE (using true())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected FALSE not TRUE (using true())';
    }

    public function getDescription()
    {
        return 'TRUE';
    }
}