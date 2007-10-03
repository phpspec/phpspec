<?php

class PHPSpec_Matcher_Equal implements PHPSpec_Matcher_Interface
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
        return $this->_expected == $this->_actual;
    }

    public function getFailureMessage()
    {
        return 'expected ' . strval($this->_expected) . ', got ' . strval($this->_actual) . ' (using equal())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . strval($this->_actual) . ' not to equal ' . strval($this->_expected) . ' (using equal())';
    }

    public function getDescription()
    {
        return 'equal ' . strval($this->_expected);
    }
}