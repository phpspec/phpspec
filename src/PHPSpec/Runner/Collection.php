<?php

class PHPSpec_Runner_Collection implements Countable
{

    protected $_context = null;
    protected $_examples = array();
    protected $_description = null;
    protected $_exampleClass = 'PHPSpec_Runner_Example';

    public function __construct(PHPSpec_Context $context, $exampleClass = null)
    {
        $this->_context = $context;
        if (!is_null($exampleClass)) {
            $this->_verifyExampleClass($exampleClass);
            $this->_exampleClass = strval($exampleClass);
        }
        $this->_buildExamples();
        $this->_description = $context->getDescription();
    }

    public function getExamples()
    {
        return $this->_examples;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function count()
    {
        return count($this->_examples);
    }

    public function execute(PHPSpec_Runner_Result $result)
    {
        if (method_exists($this->_context, 'beforeAll')) {
            $this->_context->beforeAll();
        }

        $examples = $this->getExamples();
        foreach ($examples as $example) {
            try {
                $example->execute();
                $result->addPass($example);
            } catch (PHPSpec_Runner_FailedMatcherException $e) {
                $result->addFailure($example);
            } catch (Exception $e) {
                throw $e;
            }
        }

        if (method_exists($this->_context, 'afterAll')) {
            $this->_context->afterAll();
        }
    }

    protected function _buildExamples()
    {
        $methods = $this->_context->getSpecMethods();
        foreach ($methods as $methodName) {
            $this->_addExample( new $this->_exampleClass($this->_context, $methodName) );
        }
    }

    protected function _addExample(PHPSpec_Runner_Example $example)
    {
        $this->_examples[] = $example;
    }

    protected function _verifyExampleClass($exampleClass)
    {
        $class = new ReflectionClass($exampleClass);
        if (!$class->isSubclassOf(new ReflectionClass('PHPSpec_Runner_Example'))) {
            throw new PHPSpec_Exception('not a valid PHPSpec_Runner_Example subclass');
        }
    }
}