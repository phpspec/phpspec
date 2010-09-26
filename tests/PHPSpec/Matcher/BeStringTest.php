<?php

class PHPSpec_Matcher_BeStringTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeString(THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR);
		$this->matcher->matches(1);
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnADescriptionWithExpectedValue()
	{
	    $this->assertSame('be string', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeanifulFailureMessageIfRequested()
	{
	    $this->assertSame('expected to be string, got 1 type of integer (using beString())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeanifulNegativeFailureMessageIfRequested()
	{
	    $this->assertSame('expected 1 not to be string (using beString())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnMismatch()
	{
	    $this->assertFalse($this->matcher->matches(1));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatch()
	{
	    $this->assertTrue($this->matcher->matches('string'));
	}
}