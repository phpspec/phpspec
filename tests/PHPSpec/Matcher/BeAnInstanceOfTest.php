<?php

class PHPSpec_Matcher_BeAnInstanceOfTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	public function setUp() {
		include_once 'PHPSpec/_files/Foo.php';
		include_once 'PHPSpec/_files/Bar.php';
		$this->matcher = new PHPSpec_Matcher_BeAnInstanceOf('Foo');
		$this->matcher->matches(new Bar);
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('be an instance of Foo', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeanifulFailureMessageIfRequested() {
		$this->assertSame('expected Foo, got Bar (using beAnInstanceOf())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnAMeanifulNegativeFailureMessageIfRequested() {
		$this->assertSame('expected Bar not to be Foo (using beAnInstanceOf())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->assertFalse($this->matcher->matches(new Bar));
		$this->assertFalse($this->matcher->matches(NULL));
		$this->assertFalse($this->matcher->matches('a string'));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$this->assertTrue($this->matcher->matches(new Foo));
	}	
}