<?php

class PHPSpec_Runner_Result
{

    protected $_failed = array();

    protected $_passed = array();

    public function __construct()
    {
    }

    public function execute(PHPSpec_Runner_Collection $collection)
    {
        $collection->execute($this);
    }

    public function addFailure(PHPSpec_Runner_Example $example)
    {
        $this->_failed[] = $example;
    }

    public function addPass(PHPSpec_Runner_Example $example)
    {
        $this->_passed[] = $example;
    }

    public function getFailures()
    {
        return $this->_failed;
    }

    protected function __toString()
    {
        $str = '';
        $str = count($this->_passed) . ' Specs Passed' . PHP_EOL;
        foreach ($this->_failed as $failure) {
            $str .= $failure->getContextDescription();
            $str .= ' => ' . $failure->getSpecificationText();
            $str .= ' => ' . $failure->getFailedMessage();
            $str .= PHP_EOL;
        }
        $str .= 'DONE';
        return $str;
    }

}