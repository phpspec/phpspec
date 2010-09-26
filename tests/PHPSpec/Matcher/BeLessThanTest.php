<?php

class PHPSpec_Matcher_BeLessThanTest extends PHPUnit_Framework_TestCase
{
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeLessThan(1);
		$this->matcher->matches(0);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be less than 1', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->assertSame('expected less than 1, got 0 (using beLessThan())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
		$this->assertSame('expected 0 not to be less than 1 (using beLessThan())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches(2));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches(0));
	}
}