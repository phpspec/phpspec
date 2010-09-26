<?php

class PHPSpec_Matcher_BeLessThanOrEqualToTest extends PHPUnit_Framework_TestCase {
    private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeLessThanOrEqualTo(1);
		$this->matcher->matches(2);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be less than or equal to 1', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->assertSame('expected less than or equal to 1, got 2 (using beLessThanOrEqualTo())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
		$this->assertSame('expected 2 not to be less than or equal to 1 (using beLessThanOrEqualTo())', $this->matcher->getNegativeFailureMessage());
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
		$this->assertTrue($this->matcher->matches(1));
	}
}