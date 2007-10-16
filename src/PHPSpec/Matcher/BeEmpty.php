<?php

class PHPSpec_Matcher_BeEmpty implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = null; // empty() is itself the expectation
    }

    public function matches($actual)
    {
        return empty($this->_actual);
    }

    public function getFailureMessage()
    {
        return 'expected to be empty, got not empty (using beEmpty())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected not to be empty (using beEmpty())';
    }

    public function getDescription()
    {
        return 'beEmpty';
    }
}