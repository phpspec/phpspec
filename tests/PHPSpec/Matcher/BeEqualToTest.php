<?php

class BeEqualToTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeEqualTo(1);
		$this->matcher->matches(0);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be equal to 1', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeanifulFailureMessageIfRequested() {
		$this->assertSame('expected 1, got 0 (using beEqualTo())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeanifulNegativeFailureMessageIfRequested() {
		$this->assertSame('expected 0 not to equal 1 (using beEqualTo())', $this->matcher->getNegativeFailureMessage());
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
	}
}