<?php

require_once 'PHPSpec/_files/BooSpec.php';

class PHPSpec_ContextTest extends PHPUnit_Framework_TestCase
{
	private $context;
	
	public function setUp() {
		$this->context = new describeBoo;
	}
	
	/**
	 * @test
	 */
	public function canGetNumberOfSpecsFromContext() {
		$this->assertTrue($this->context->getSpecificationCount() === 2);
	}
	
	/**
	 * @test
	 */
	public function catRetrieveAnArrayOfSpecMethods() {
        $expected = array('itShouldBeTrue', 'itShouldBeFalse');
		$this->assertTrue($this->context->getSpecMethods() === $expected);
	}
	
	/**
	 * @test
	 */
	public function catSetAndRetrieveDescription() {
		$this->context->setDescription('abc');
        $this->assertTrue($this->context->getDescription() === "abc");
	}
	
	/**
	 * @test
	 */
	public function getCurrentSpecificationReturnSpecificationObject() {
		include_once 'PHPSpec/_files/Foo.php';
		$this->context->spec(new Foo);
		$this->assertTrue($this->context->getCurrentSpecification() instanceof PHPSpec_Specification);
	}
	
	/**
	 * @test
	 */
	public function implementsCountableSplInterface() {
		$this->assertTrue(count($this->context) === 2);
	}
	
	/**
	 * @test
	 */
	public function shouldBuildDetailsOfDescription() {
		include_once 'PHPSpec/_files/EmptyArraySpec.php';
		$context = new describeEmptyArray;
		$this->assertTrue($context->getDescription() === "describe empty array");
	} 
	
	/**
	 * @test
	 */
	public function shouldGetFilePathForCurrentContextClass() {
		$fp = realpath(dirname(__FILE__) . '/_files/BooSpec.php');
        $this->assertSame($fp, $this->context->getFileName());
	}
	
	/**
	 * @test
	 */
	public function specMethodReturnsASpecificationObject() {
		include_once 'PHPSpec/_files/Foo.php';
		$this->context->spec(new Foo);
		$this->assertTrue($this->context->spec(new Foo) instanceof PHPSpec_Specification);
	}
}