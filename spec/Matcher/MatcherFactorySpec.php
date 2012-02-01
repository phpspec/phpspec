<?php

use \PHPSpec\Matcher\MatcherFactory;

class DescribeMatcherFactory extends \PHPSpec\Context
{
    
    private $_localMatcherFactory;
    
    public function before()
    {
        $this->_localMatcherFactory = $this->spec(new MatcherFactory);
    }
    
    
    public function itCreatesBuiltinMatchers()
    {
        foreach ($this->builtInMatchers() as $matcher) {
            $this->_localMatcherFactory->create($matcher, true)
                 ->should
                 ->beAnInstanceOf('PHPSpec\Matcher\\' . strtoupper($matcher[0]) . substr($matcher, 1));
        }
    }
    
    public function itThrowsAnExceptionWhenMatcherDoesntExist()
    {
        $factory = new MatcherFactory;
        $this->spec(function() use ($factory){
             $factory->create('BeChuckNorris', 'Chuck Norris');
        })->should->throwException('PHPSpec\Matcher\InvalidMatcher', 'Call to undefined method BeChuckNorris');
       
    }
    
    public function itForwardsArrayOfValuesAsListOfArgumentsForMatcher()
    {
        $matcher = $this->_localMatcherFactory->create('throwException', array('\Exception', 'Does not work'));
        $matcher->property('_expectedException')->should->be('\Exception');
        $matcher->property('_expectedMessage')->should->be('Does not work');
    }
    
    
    public function itCreatesATreeOfMatcherFilesConsistentWithTheFilesOnTheIncludeMatcherPath()
    {
        
    }

    public function itIsAbleToUseCustomMatchers()
    {
        
    }
    
    public function itAllowsACustomMatcherToOverrideABuiltinMatcher()
    {
        
    }
    
    private function builtInMatchers()
    {
        return array(
            'be', 'beAnInstanceOf', 'beEmpty', 'beEqualTo', 'beFalse',
            'beGreaterThan', 'beGreaterThanOrEqualTo', 'beInteger',
            'beLessThan', 'beLessThanOrEqualTo', 'beNull', 'beString', 'beTrue',
            'equal', 'match', 'throwException'
        );
    }
}