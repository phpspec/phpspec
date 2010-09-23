<?php

class BeTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_Be(1);
		$this->matcher->matches(0);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be 1', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeanifulFailureMessageIfRequested() {
		$this->assertSame('expected 1, got 0 (using be())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeanifulNegativeFailureMessageIfRequested() {
		$this->assertSame('expected 0 not to be 1 (using be())', $this->matcher->getNegativeFailureMessage());
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