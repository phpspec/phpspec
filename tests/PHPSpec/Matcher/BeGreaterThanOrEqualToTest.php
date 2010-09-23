<?php

class BeGreaterThanOrEqualToTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeGreaterThanOrEqualTo(1);
		$this->matcher->matches(0);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be greater than or equal to 1', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->assertSame('expected greater than or equal to 1, got 0 (using beGreaterThanOrEqualTo())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
		$this->assertSame('expected 0 not to be greater than or equal to 1 (using beGreaterThanOrEqualTo())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches(0));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches(1));
		$this->assertTrue($this->matcher->matches(2));
	}
}