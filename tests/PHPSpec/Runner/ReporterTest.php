<?php

class ReporterTest extends \PHPUnit_Framework_TestCase {
    /**
     * @test
     */
	public function itCreatesAConsoleResultByDefault()
	{
		$result = $this->getMock('\PHPSpec\Runner\Result');
		$reporter = \PHPSpec\Runner\Reporter::create($result);
		$this->assertTrue($reporter instanceof \PHPSpec\Runner\Reporter\Console);
	}

	/**
	 * @test
	 */
	public function itCreatesAConsoleResultWhenConsuleIsPassed()
	{
		$result = $this->getMock('\PHPSpec\Runner\Result');
		$reporter = \PHPSpec\Runner\Reporter::create($result, 'Console');
		$this->assertTrue($reporter instanceof \PHPSpec\Runner\Reporter\Console);
	}

	/**
	 * @test
	 */
	public function itCreatesAHtmlResultWhenHtmlIsPassed()
	{
		$result = $this->getMock('\PHPSpec\Runner\Result');
		$reporter = \PHPSpec\Runner\Reporter::create($result, 'Html');
		$this->assertTrue($reporter instanceof \PHPSpec\Runner\Reporter\Html);
	}
}

