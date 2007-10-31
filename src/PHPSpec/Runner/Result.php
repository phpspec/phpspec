<?php

class PHPSpec_Runner_Result implements Countable
{

    protected $_failed = array();

    protected $_passed = array();

    protected $_exceptions = array();

    protected $_specCount = 0;

    public function execute(PHPSpec_Runner_Collection $collection)
    {
        $collection->execute($this);
    }

    public function addFailure(PHPSpec_Runner_Example $example)
    {
        $this->_failed[] = $example;
    }

    public function addException(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_exceptions[] = array($example, $e);
    }

    public function addPass(PHPSpec_Runner_Example $example)
    {
        $this->_passed[] = $example;
    }

    public function getFailures()
    {
        return $this->_failed;
    }

    public function getExceptions()
    {
        return $this->_exceptions;
    }

    public function getPasses()
    {
        return $this->_failed;
    }

    public function addSpecCount($count = 1)
    {
        $this->_specCount += intval($count);
    }

    public function setSpecCount($count)
    {
        $this->_specCount = intval($count);
    }

    public function getSpecCount()
    {
        return $this->_specCount;
    }

    public function count()
    {
        return $this->getSpecCount();
    }

}