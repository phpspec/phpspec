<?php

namespace Spec\PHPSpec\Specification;

use \PHPSpec\Specification\ExampleRunner;

class DescribeExampleRunner extends \PHPSpec\Context
{
    const NUM_OF_METHODS_EXAMPLE_HAS = 2;
    
    function before()
    {
        $this->exampleRunner = $this->spec(new ExampleRunner);
        $this->reporter = $this->getReporter();
        $this->example = $this->getExample();
        $this->exampleFactory = $this->getExampleFactory();
    }
    
    function itWillCallCreateForEachExampleOfTheGroup()
    {
        $this->exampleFactory->shouldReceive('create')
                             ->times(self::NUM_OF_METHODS_EXAMPLE_HAS)
                             ->andReturn($this->example);
        $this->exampleRunner->setExampleFactory($this->exampleFactory);
        
        $this->exampleRunner->run(new Fake, $this->reporter);
    }
    
    function getReporter()
    {
        $reporter = $this->mock('PHPSpec\Runner\Reporter');
        $reporter->shouldReceive('exampleGroupStarted');
        $reporter->shouldReceive('exampleGroupFinished');
        return $reporter;
    }
    
    function getExample()
    {
        $example = $this->mock('PHPSpec\Specification\Example');
        $example->shouldReceive('run');
        return $example;
    }
    
    function getExampleFactory()
    {
        return $this->mock('PHPSpec\Specification\ExampleFactory');
    }
}

class Fake extends \PHPSpec\Context {
    function itShouldBeCalled() {}
    function itShouldBeCalledTo() {}
}