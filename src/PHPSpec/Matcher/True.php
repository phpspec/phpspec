<?php

class PHPSpec_Matcher_True implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = true;
    }

    public function matches($actual)
    {
        $this->_actual = $actual;
        if (!is_bool($actual)) {
            return false;
        }
        return $this->_expected === $this->_actual;
    }

    public function getFailureMessage()
    {
        return 'expected TRUE, got FALSE or non-boolean (using true())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected FALSE or non-boolean not TRUE (using true())';
    }

    public function getDescription()
    {
        return 'TRUE';
    }
}