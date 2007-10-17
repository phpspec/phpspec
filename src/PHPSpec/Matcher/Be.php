<?php

class PHPSpec_Matcher_Be extends PHPSpec_Matcher_Equal implements PHPSpec_Matcher_Interface
{

    public function getFailureMessage()
    {
        return 'expected ' . strval($this->_expected) . ', got ' . strval($this->_actual) . ' (using be())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . strval($this->_actual) . ' not to be ' . strval($this->_expected) . ' (using be())';
    }

    public function getDescription()
    {
        return 'be ' . strval($this->_expected);
    }
}