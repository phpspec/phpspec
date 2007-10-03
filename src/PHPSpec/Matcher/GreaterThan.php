<?php

class PHPSpec_Matcher_GreaterThan implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = $expected;
    }

    public function matches($actual)
    {
        $this->_actual = $actual;
        return $this->_expected < $this->_actual;
    }

    public function getFailureMessage()
    {
        return 'expected greater than ' . strval($this->_expected) . ', got ' . strval($this->_actual) . ' (using greaterThan())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . strval($this->_actual) . ' not to be greater than ' . strval($this->_expected) . ' (using greaterThan())';
    }

    public function getDescription()
    {
        return 'greater than ' . strval($this->_expected);
    }
}