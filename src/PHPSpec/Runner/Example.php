<?php

class PHPSpec_Runner_Example
{

    protected $_context = null;
    protected $_methodName = null;
    protected $_specificationText = null;
    protected $_failedMessage = null;

    public function __construct(PHPSpec_Context $context, $methodName)
    {
        $this->_context = $context;
        $this->_methodName = $methodName;
        $this->_specificationText = $this->_setSpecificationText($this->_methodName);
    }

    public function getMethodName()
    {
        return $this->_methodName;
    }

    public function getSpecificationText()
    {
        return $this->_specificationText;
    }

    public function getContextDescription()
    {
        return $this->_context->getDescription();
    }

    public function getSpecificationBeingExecuted()
    {
        if (is_null($this->_specificationBeingExecuted)) {
            throw new PHPSpec_Exception('cannot return a PHPSpec_Specification until the example is executed');
        }
        return $this->_specificationBeingExecuted;
    }

    public function getFailedMessage()
    {
        if (is_null($this->_failedMessage)) {
            throw new PHPSpec_Exception('cannot return a failure message until the example is executed');
        }
        return $this->_failedMessage;
    }

    public function execute() // add error/exception catchers
    {
        $this->_context->{$this->_methodName}();
        $this->_specificationBeingExecuted = $this->_context->getCurrentSpecification();
        $expected = $this->_specificationBeingExecuted->getExpectation()->getExpectedMatcherResult();
        $actual = $this->_specificationBeingExecuted->getMatcherResult();
        if ($expected !== $actual) { // ===
            if ($expected === true) {
                $this->_failedMessage = $this->_specificationBeingExecuted->getMatcherFailureMessage();
            } else {
                $this->_failedMessage = $this->_specificationBeingExecuted->getMatcherNegativeFailureMessage();
            }
            throw new PHPSpec_Runner_FailedMatcherException(); // add spec data later
        }
    }

    protected function _setSpecificationText($methodName)
    {
        $methodName = substr($methodName, 2);
        $terms = preg_split("/(?=[[:upper:]])/", $methodName, -1, PREG_SPLIT_NO_EMPTY);
        $termsLowercase = array_map('strtolower', $terms);
        return implode(' ', $termsLowercase);
    }
}