<?php

class PHPSpec_Matcher_MatchTest extends \PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new \PHPSpec\Matcher\Match("/bar/");
		$this->matcher->matches('bar');
	}
	/**
	 * @test
	 **/
	public function itShouldReturnADescriptionWithExpectedValue() {
	    $this->assertSame('match \'/bar/\' PCRE regular expression', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->matcher->matches('foo');
		$this->assertSame('expected match for \'/bar/\' PCRE regular expression, got \'foo\' (using match())', $this->matcher->getFailureMessage());
	}
	
	/**
	* @test
	*/
    public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
	    $this->assertSame('expected no match for \'/bar/\' PCRE regular expression, got \'bar\' (using match())', $this->matcher->getNegativeFailureMessage());
    }
		
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches('foo'));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches('bar'));
	}
}