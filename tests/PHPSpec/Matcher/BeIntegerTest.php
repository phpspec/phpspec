<?php

class BeIntegerTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeInteger(THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR);
		$this->matcher->matches("hello world");
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be integer', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->assertSame('expected to be integer, got hello world type of string (using beInteger())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
		$this->assertSame('expected hello world not to be integer (using beInteger())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches("hello world"));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches(123));
	}
}