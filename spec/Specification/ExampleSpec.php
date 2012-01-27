<?php

class FakeExampleGroup extends \PHPSpec\Specification\ExampleGroup {
	
	public function itShouldRunTheTest() {
		
	}

}

class DescribeExample extends \PHPSpec\Context {
	
	private $_example;
	private $_exampleGroup;
	private $_reporter;

	public function before() {
		$this->_reporter = $this->mock('PHPSpec\Runner\Reporter');
		$this->_reporter->shouldReceive('addPass');

		$this->_exampleGroup = new FakeExampleGroup();

		$this->_example = $this->spec( new \PHPSpec\Specification\Example(
			$this->_exampleGroup,
			'itShouldRunTheTest'
		));
		$this->_example->run($this->_reporter);
	}

	public function itHasFileNameOfExampleGroupAfterRun() {
		$this->_example->getFile()->should->be(
			__FILE__
		);
	}

	public function itHasLineOfExampleAfterRun() {
		$this->_example->getLine()->should->be(5);
	}

}