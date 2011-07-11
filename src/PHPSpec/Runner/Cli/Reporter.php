<?php

namespace PHPSpec\Runner\Cli;

use \PHPSpec\Runner\Formatter,
    \PHPSpec\Specification\Result\Failure,
    \PHPSpec\Specification\Result\Error,
    \PHPSpec\Specification\Result\Exception,
    \PHPSpec\Specification\Result\Pending,
    \PHPSpec\Specification\Result\DeliberateFailure,
    \PHPSpec\Specification\Example,
    \PHPSpec\Util\Backtrace;

class Reporter extends \PHPSpec\Runner\Reporter
{
    protected $_message = '';
    protected $_formatter;
    protected $_failFast;
    
    public function addFailure(Example $example, Failure $failure)
    {
        $this->_failures->attach($failure, $example);
        $this->notify('status', 'F', $example->getSpecificationText(),
                      $failure->getMessage(),
                      Backtrace::pretty($failure->getTrace()), $failure);
        
        $this->_checkFailFast();
    }
    
    public function addPass(Example $example)
    {
        $this->_passing[] = $example;
        $this->notify('status', '.', $example->getSpecificationText());
    }
    
    public function addDeliberateFailure(Example $example,
                                         DeliberateFailure $failure)
    {
        $this->_failures->attach($failure, $example);
        $this->notify('status', 'F', $example->getSpecificationText(),
                      $failure->getMessage(),
                      Backtrace::pretty($failure->getTrace()), $failure);
        $this->_checkFailFast();
    }
    
    public function addError(Example $example, Error $error)
    {
        $this->_errors->attach($error, $example);
        $this->notify('status', 'E', $example->getSpecificationText(),
                      $error->getMessage(),
                      Backtrace::pretty($error->getTrace()), $error);
        $this->_checkFailFast();
    }
    
    public function addException(Example $example, \Exception $e)
    {
        $this->_exceptions->attach($e, $example);
        $this->notify('status', 'E', $example->getSpecificationText(),
                      $e->getMessage(), Backtrace::pretty($e->getTrace()), $e);
        $this->_checkFailFast();
    }
    
    public function addPending(Example $example, Pending $pending)
    {
        $this->_pendingExamples->attach($pending, $example);
        $this->notify(
            'status', '*', $example->getSpecificationText(),
            $pending->getMessage()
        );
    }
    
    public function setMessage($string, $newLine = true)
    {
        $this->_message .= $string . ($newLine ? PHP_EOL : '');
    }
    
    public function getMessage()
    {
        return $this->_message;
    }
    
    public function hasMessage()
    {
        return (bool)strlen($this->_message);
    }
    
    public function getFormatter()
    {
        return $this->_formatter;
    }
    
    public function setFormatter(Formatter $formatter)
    {
        $this->_formatter = $formatter;
    }
    
    public function getFailFast()
    {
        return $this->_failFast;
    }
    
    public function setFailFast($boolean)
    {
        $this->_failFast = $boolean;
    }
    
    private function _checkFailFast()
    {
        if($this->getFailFast() === true) {
            $this->notify('exit');
        }
    }
}