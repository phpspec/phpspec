<?php

class ExpectationTest extends PHPUnit_Framework_TestCase {
	private $expect;
	
	public function setUp() {
		$this->expect = new PHPSpec_Expectation;
	}
	
	/**
	 * @test
	 */
	public function itShouldHaveAStringCastValueOfShouldNot() {
		$this->assertSame('should not', (string)$this->expect->shouldNot());
	}
	
	/**
	 * @test
	 */
	public function itShouldHaveAStringCastValueOfShould() {
		$this->assertSame('should', (string)$this->expect->should());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnInstanceOfSelfWhenSettingNegativeExpectation() {
		$returned = $this->expect->shouldNot();
		$this->assertSame($returned, $this->expect);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnInstanceOfSelfWhenSettingPositiveExpectation() {
		$returned = $this->expect->should();
		$this->assertSame($returned, $this->expect);
	}
	
	/**
	 * @test
	 */
	public function itShouldSetupSpecToExpectAMatcherToHaveAFalseResult() {
		$this->expect->shouldNot();
		$this->assertFalse($this->expect->getExpectedMatcherResult());
	}
	
	/**
	 * @test
	 */
	public function itShouldSetupSpecToExpectAMatcherToHaveATrueResult() {
		$this->expect->should();
		$this->assertTrue($this->expect->getExpectedMatcherResult());
	}
}