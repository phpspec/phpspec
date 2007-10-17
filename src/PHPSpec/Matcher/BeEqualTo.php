<?php

class PHPSpec_Matcher_BeEqualTo extends PHPSpec_Matcher_Equal implements PHPSpec_Matcher_Interface
{

    public function getFailureMessage()
    {
        return 'expected ' . strval($this->_expected) . ', got ' . strval($this->_actual) . ' (using beEqualTo())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . strval($this->_actual) . ' not to equal ' . strval($this->_expected) . ' (using beEqualTo())';
    }

    public function getDescription()
    {
        return 'be equal to ' . strval($this->_expected);
    }
}