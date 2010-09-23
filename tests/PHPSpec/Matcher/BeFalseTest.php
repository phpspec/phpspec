<?php

class BeFalseTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeFalse(THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR);
		$this->matcher->matches(FALSE);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be FALSE', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->assertSame('expected FALSE, got TRUE or non-boolean (using beFalse())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
		$this->assertSame('expected TRUE or non-boolean not FALSE (using beFalse())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches(TRUE));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches(FALSE));
	}
}