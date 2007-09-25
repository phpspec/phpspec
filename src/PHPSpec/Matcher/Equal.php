<?php

require_once 'PHPSpec/Matcher/Interface.php';

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
}