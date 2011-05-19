<?php

class PHPSpec_Matcher_ThrowExceptionTest extends \PHPUnit_Framework_TestCase {
    private $matcher;
	
    public function setUp() {
		$this->matcher = new \PHPSpec\Matcher\ThrowException('InvalidArgumentException');
    }
    
    /**
	 * @test
	 */
	public function itShouldReturnADescriptionWithExpectedValue() {
		$this->assertSame('throw exception InvalidArgumentException', $this->matcher->getDescription());
	}
	
    /**
	 * @test
	 */
	public function itShouldReturnAMeaningfulFailureMessageIfRequested() {
	    $this->matcher->matches('BadMethodCallException');
		$this->assertSame('expected to throw exception \'InvalidArgumentException\', got \'BadMethodCallException\' (using throwException())', $this->matcher->getFailureMessage());
	}
	
    /**
	 * @test
	 */
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired() {
	    $this->matcher->matches('BadMethodCallException');
		$this->assertSame('expected \'BadMethodCallException\' not to be thrown but got \'InvalidArgumentException\' (using throwException())', $this->matcher->getNegativeFailureMessage());
	}
}