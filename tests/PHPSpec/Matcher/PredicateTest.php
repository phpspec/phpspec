<?php

class PHPSpec_Matcher_PredicateTest extends PHPUnit_Framework_TestCase {
	private $predicate;
	
	public function setUp() {
		$this->predicate = new PHPSpec_Matcher_Predicate(true);
		$this->predicate->setMethodName('hasArg1');
		$this->predicate->setObject(new Foo);
		$this->predicate->setPredicateCall('haveArg1');
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnADescriptionOfTheExpectation() {
	    $this->assertSame('have arg1', $this->predicate->getDescription());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeaningfulFailureMessageIfRequested()
	{
	    $this->assertSame('expected TRUE, got FALSE or non-boolean (using haveArg1())'
	                      , $this->predicate->getFailureMessage());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeaningulNegativeFailureMessageIfRequired()
	{
	    $this->assertSame('expected FALSE or non-boolean not TRUE (using haveArg1())'
	                      , $this->predicate->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnFalseOnMismatch() {
		$this->predicate->setObject(new Foo);
		$this->assertFalse($this->predicate->matches('unused_param'));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseIfPredicateDoesntReturnBoolean()
	{
		$this->predicate = new PHPSpec_Matcher_Predicate(true);
		$this->predicate->setMethodName('getArg1');
		$this->predicate->setObject(new Foo('not boolean'));
		$this->predicate->setPredicateCall('canGetArg1');
	    $this->assertFalse($this->predicate->matches('unused_param'));
	}
	
	/**
	 * @test
	 */
	public function itShouldReturnTrueOnMatch() {
		$foo = new Foo('something');
		$this->predicate->setObject($foo);
		$this->assertTrue($this->predicate->matches('unused_param'));
	}
	
	/**
	 * @test
	 **/
	public function itShouldThrowAnExceptionWhenTryingToSetObjectWithSomethingElse()
	{
		$this->setExpectedException('PHPSpec_Exception');
		$this->predicate->setObject(null);
	}
}