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
        $includePath = get_include_path();
        $extraIncludePath = __DIR__ . DIRECTORY_SEPARATOR . "_files";
        set_include_path($includePath . PATH_SEPARATOR . $extraIncludePath);
        $this->_localMatcherFactory = $this->spec(new MatcherFactory(
             array('CustomMatchers')));
        
        $this->_localMatcherFactory->create('beAnInstanceOf', true);

        $matchersArray = $this->_localMatcherFactory->property('_matchers');


        $matchersArray['DummyMatcher']->should->be('CustomMatchers\\');

        set_include_path($includePath);
    }

    public function itLoadsMatchersFromSubfolders()
    {
        $includePath = get_include_path();
        $extraIncludePath = __DIR__ . DIRECTORY_SEPARATOR . "_files";
        set_include_path($includePath . PATH_SEPARATOR . $extraIncludePath);
        $this->_localMatcherFactory = $this->spec(new MatcherFactory(
             array('CustomMatchers')));

        $this->_localMatcherFactory->create('beAnInstanceOf', true);

        $matchersArray = $this->_localMatcherFactory->property('_matchers');

        $matchersArray['SubDummyMatcher']->should->be('CustomMatchers\SubMatchers\\');

        set_include_path($includePath);
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