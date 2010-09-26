<?php

class PHPSpec_Matcher_EqualTest extends PHPUnit_Framework_TestCase {
	private $matcher;
	
	/**
	 * @test
	 **/
	public function itShouldReturnADescriptionWithExpectedValue()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(1);
		$this->matcher->matches(0);
	    $this->assertSame('equal 1', $this->matcher->getDescription());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeanifulFailureMessageIfRequested()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(1);
		$this->matcher->matches(0);
	    $this->assertSame('expected 1, got 0 (using equal())', $this->matcher->getFailureMessage());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnAMeanifulNegativeFailureMessageIfRequested()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(1);
		$this->matcher->matches(0);
	    $this->assertSame('expected 0 not to equal 1 (using equal())', $this->matcher->getNegativeFailureMessage());
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnMismatch()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(1);
	    $this->assertFalse($this->matcher->matches(0));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatch()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(1);
	    $this->assertTrue($this->matcher->matches(1));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnMismatchedArrayType()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(1);
	    $this->assertFalse($this->matcher->matches(array()));
	    $this->assertFalse($this->matcher->matches(array(1)));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnMismatchedClassType()
	{
		include_once dirname(dirname(__FILE__)) . '/_files/Foo.php';
		$this->matcher = new PHPSpec_Matcher_Equal(new stdClass);
	    $this->assertFalse($this->matcher->matches(new Foo));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnMismatchedClassTypes()
	{
		include_once dirname(dirname(__FILE__)) . '/_files/Foo.php';
		$this->matcher = new PHPSpec_Matcher_Equal(new stdClass);
	    $this->assertFalse($this->matcher->matches(new Foo));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnMismatchedObjectType()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(new stdClass);
	    $this->assertFalse($this->matcher->matches(array()));
	    $this->assertFalse($this->matcher->matches(1));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnNonMatchingObjects()
	{
		$obj1 = new stdClass;
		$obj2 = new stdClass;
		$obj1->hasFoo = true;
		$obj2->hasFoo = false;
	    $this->matcher = new PHPSpec_Matcher_Equal($obj1);
	    $this->assertFalse($this->matcher->matches($obj2));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnNonMatchingArrays()
	{
	    $this->matcher = new PHPSpec_Matcher_Equal(array(1,2,3));
	    $this->assertFalse($this->matcher->matches(array(1,2,4)));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnNonMatchingStrings()
	{
	    $this->matcher = new PHPSpec_Matcher_Equal("a string");
	    $this->assertFalse($this->matcher->matches("another"));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnFalseOnNonMatchingFloats()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(0.123);
	    $this->assertFalse($this->matcher->matches(0.125, 0.0001));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatchingArrays()
	{
	    $this->matcher = new PHPSpec_Matcher_Equal(array(1,2,3));
	    $this->assertTrue($this->matcher->matches(array(1,2,3)));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatchingObjects()
	{
		$obj = new stdClass;
	    $this->matcher = new PHPSpec_Matcher_Equal($obj);
	    $this->assertTrue($this->matcher->matches($obj));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatchingFloats()
	{
		$this->matcher = new PHPSpec_Matcher_Equal(0.123);
	    $this->assertTrue($this->matcher->matches(0.123, 0.0001));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatchingString()
	{
		$this->matcher = new PHPSpec_Matcher_Equal('a string');
	    $this->assertTrue($this->matcher->matches('a string'));
	}
	
	/**
	 * @test
	 **/
	public function itShouldReturnTrueOnMatchingResource()
	{
		$fh = fopen('php://input', 'r');
		$this->matcher = new PHPSpec_Matcher_Equal($fh);
	    $this->assertTrue($this->matcher->matches($fh));
	    fclose($fh);
	}
}