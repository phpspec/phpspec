<?php

class PHPSpec_Matcher_BeEmptyTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	const SOMETHING = 'something';
	
	public function setUp() {
		$this->matcher = new PHPSpec_Matcher_BeEmpty(THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR);
		$this->matcher->matches(self::SOMETHING);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be empty', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
		$this->assertSame('expected to be empty, got not empty (using beEmpty())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
		$this->assertSame('expected not to be empty (using beEmpty())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches(self::SOMETHING));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches(null));
	}
}