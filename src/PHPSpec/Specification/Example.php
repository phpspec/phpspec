<?php

namespace PHPSpec\Specification;

use \PHPSpec\Runner\Reporter,
    \PHPSpec\Specification\Result\Exception,
    \PHPSpec\Specification\Result\Error,
    \PHPSpec\Specification\Result\Pending,
    \PHPSpec\Specification\Result\DeliberateFailure,
    \PHPSpec\Specification\Result\Failure;

class Example
{
    protected $_example;
    protected $_exampleGroup;
    
    public function __construct(ExampleGroup $exampleGroup,
                                \ReflectionMethod $example)
    {
        $this->_example = $example;
        $this->_exampleGroup = $exampleGroup;
    }
    
    public function run(Reporter $reporter)
    {
        try {
            $methodName = $this->_example->getName();
            call_user_func(array($this->_exampleGroup, 'before'));
            call_user_func(array($this->_exampleGroup, $methodName));
            call_user_func(array($this->_exampleGroup, 'after'));
        } catch (Failure $failure) {
            $reporter->addFailure($this, $failure);
            return;
        } catch(Pending $pending) {
            $reporter->addPending($this, $pending);
            return;
        } catch(Error $error) {
            $reporter->addError($this, $error);
            return;
        } catch(\Exception $e) {
            $reporter->addException($this, new Exception($e));
            return;
        }
        $reporter->addPass($this);
    }
    
    public function getDescription()
    {
        $class = str_replace('Describe', '', get_class($this->_exampleGroup));
        return "$class " . $this->getSpecificationText();
    }
    
    /**
     * Sets the specification text taken from method name
     * 
     * @param string $methodName
     * @return string
     */
    public function getSpecificationText()
    {
        $methodName = substr($this->_example->getName(), 2);
        $terms = preg_split(
            "/(?=[[:upper:]])/", $methodName, -1, PREG_SPLIT_NO_EMPTY
        );
        $termsLowercase = array_map('strtolower', $terms);
        return implode(' ', $termsLowercase);
    }
}