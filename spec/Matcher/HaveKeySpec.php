<?php

namespace Spec\PHPspec\Matcher;

use \PHPSpec\Matcher\HaveKey;

class DescribeHaveKey extends \PHPSpec\Context {
    private $matcher;
	
    function before()
    {
		$this->matcher = $this->spec(new HaveKey(array('foo' => 42)));
		$this->matcher->matches('foo');
    }
    
	function itShouldReturnADescriptionWithExpectedValue()
    {
		$this->matcher->getDescription()->should->be('have key \'foo\'');
	}
	
	function itShouldReturnAMeaningfulFailureMessageIfRequested()
    {
	    $this->matcher->matches('bar');
	    $this->matcher->getFailureMessage()->should->be(
	        'expected to have key \'bar\', got key does not exist (using haveKey())'
	    );
	}
	
	function itShouldReturnAMeaningulNegativeFailureMessageIfRequired()
    {
	    $this->matcher->matches('foo');
		$this->matcher->getNegativeFailureMessage()->should->be(
		    'expected key \'foo\' not to exist (using haveKey())'
		);
	}
	
	function itReturnsTrueIfKeyExists()
	{
	    $this->matcher->matches('foo')->should->beTrue();
	}
	
	function itReturnsFalseIfKeyDoesNotExist()
	{
	    $this->matcher->matches('zoo')->should->beFalse();
	}
}