<?php

class PHPSpec_Matcher_BeNullTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeNull(THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR);
		$this->matcher->matches(1);
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnADescriptionWithExpectedValue()
	{
	    $this->assertSame('be NULL', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeanifulFailureMessageIfRequested()
	{
	    $this->assertSame('expected to be NULL, got 1 (using beNull())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeanifulNegativeFailureMessageIfRequested()
	{
	    $this->assertSame('expected not to be NULL (using beNull())', $this->matcher->getNegativeFailureMessage());
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
	    $this->assertTrue($this->matcher->matches(NULL));
	}
}