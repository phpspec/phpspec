<?php

class PHPSpec_Matcher_BeFalse implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = false;
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
        return 'expected FALSE, got TRUE or non-boolean (using beFalse())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected TRUE or non-boolean not FALSE (using beFalse())';
    }

    public function getDescription()
    {
        return 'be FALSE';
    }
}