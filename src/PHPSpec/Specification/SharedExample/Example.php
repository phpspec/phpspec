<?php

namespace PHPSpec\Specification\SharedExample;

use PHPSpec\Specification\Example as SpecificationExample;
use PHPSpec\Specification\Result\Pending;

use PHPSpec\Runner\Reporter;

use PHPSpec\Util\ReflectionMethod;

class Example extends SpecificationExample
{
    protected $_sharedExample;
    
    public function __construct($exampleGroup, $sharedExample, $methodName)
    {
        $this->_exampleGroup = $exampleGroup;
        $this->_sharedExample = $sharedExample;
        $this->_methodName = $methodName;
    }
    
    /**
     * Runs the example
     */
    protected function runExample($reporter)
    {
        $this->_exampleGroup->runSharedExample($this->_methodName);
    }
    
    /**
     * Marks example as pending if it is empty
     */
    protected function markExampleAsPendingIfItIsEmpty()
    {
        $method = new ReflectionMethod(
            $this->_sharedExample, $this->_methodName
        );
        if ($method->isEmpty()) {
            throw new Pending('empty example');
        }
    }
}