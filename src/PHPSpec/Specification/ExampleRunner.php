<?php

namespace PHPSpec\Specification;

use \PHPSpec\Runner\Reporter;

class ExampleRunner
{
    protected $_exampleFactory;
    protected $_interceptorFactory;
    
    public function run(ExampleGroup $exampleGroup, Reporter $reporter)
    {
        $reporter->exampleGroupStarted($exampleGroup);
        $this->runExamples($exampleGroup, $reporter);
        $reporter->exampleGroupFinished($exampleGroup);
    }
    
    protected function runExamples(ExampleGroup $exampleGroup, Reporter $reporter)
    {
        $object = new \ReflectionObject($exampleGroup);
        foreach ($object->getMethods() as $method) {
            $name = $method->getName();
            if (strtolower(substr($name, 0, 2)) === 'it') {
                $this->createExample($exampleGroup, $method)->run($reporter);
            }
        }
    }
    
    protected function createExample(ExampleGroup $exampleGroup, \ReflectionMethod $example)
    {
        return $this->getExampleFactory()->create($exampleGroup, $example);
    }
    
    public function getExampleFactory()
    {
        if ($this->_exampleFactory === null) {
            $this->_exampleFactory = new ExampleFactory;
        }
        return $this->_exampleFactory;
    }
    
    public function setExampleFactory(ExampleFactory $factory)
    {
        $this->_exampleFactory = $factory;
    }
}