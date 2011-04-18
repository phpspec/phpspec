<?php

class InterrogatorTest extends \PHPUnit_Framework_TestCase {
	private $interrogator;
	
	public function setUp() {
		include_once 'PHPSpec/_files/Foo.php';
		$this->interrogator = new \PHPSpec\Object\Interrogator('Foo', 1);
	}
	
	/**
	 * @test
	 */
	public function itShouldAcceptExistingSourceObjectWhenInstantiated() {
		$foo = new Foo;
		$this->interrogator = new \PHPSpec\Object\Interrogator($foo);
		$this->assertTrue(is_a($this->interrogator->getSourceObject(), "Foo"));		
	}
	
	/**
	 * @test
	 */
	public function itShouldAcceptStringNamingTheSourceObject() {
		$this->interrogator = new \PHPSpec\Object\Interrogator('Foo');
		$this->assertTrue(is_a($this->interrogator->getSourceObject(), "Foo"));		
	}
	
	/**
	 * @test
	 */
	public function itShouldRejectStringNamingNonExistingSourceObject() {
		include_once 'PHPSpec/_files/NonInstantiable.php';
		$this->setExpectedException("\\PHPSpec\\Exception");
		$this->interrogator = new \PHPSpec\Object\Interrogator('NonInstantiable');
	}
	
	/**
	 * @test
	 */
	public function itShouldRejectAnythingElseApartFromStringOrObject() {
		$this->setExpectedException("\\PHPSpec\\Exception");
		$this->interrogator = new \PHPSpec\Object\Interrogator();
		$this->interrogator = new \PHPSpec\Object\Interrogator(1);
		$this->interrogator = new \PHPSpec\Object\Interrogator(TRUE);
	}
	
	/**
	 * @test
	 */
	public function itShouldConstructSourceObjectViaGetSpecWithOptionalArguments() {
		$this->assertTrue(is_a($this->interrogator->getSourceObject(), "Foo"));
	}
	
	/**
	 * @test
	 */
	public function itShouldProxyMemberGetterCallsToTheSourceObject() {
		$this->assertTrue($this->interrogator->arg1 === 1);
	}
	
	/**
	 * @test
	 */
	public function itShouldProxyMemberIssetCallsToTheSourceObject() {
		$this->interrogator->arg1 = null;
        $this->assertFalse(isset($this->interrogator->arg1));
	}
	
	/**
	 * @test
	 */
	public function itShouldProxyMemberSetterCallsToTheSourceObject() {
		$this->interrogator->arg1 = 2;
        $this->assertSame(2, $this->interrogator->arg1);
	}
	
	/**
	 * @test
	 */
	public function itShouldProxyMemberUnsetCallsToTheSourceObject() {
		unset($this->interrogator->arg1);
		$this->assertFalse(isset($this->interrogator->arg1));
	}
	
	/**
	 * @test
	 */
	public function itShouldProxyMethodCallsToTheSourceObject() {
        $this->assertSame(1, $this->interrogator->getArg1());
	}
}