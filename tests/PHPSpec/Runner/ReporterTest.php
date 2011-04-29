<?php

class ReporterTest extends \PHPUnit_Framework_TestCase {
    /**
     * @test
     */
	public function itCreatesATextResultByDefault()
	{
		$result = $this->getMock('\PHPSpec\Runner\Result');
		$reporter = \PHPSpec\Runner\Reporter::create($result);
		$this->assertTrue($reporter instanceof \PHPSpec\Runner\Reporter\Text);
	}

	/**
	 * @test
	 */
	public function itCreatesATextResultWhenTextIsPassed()
	{
		$result = $this->getMock('\PHPSpec\Runner\Result');
		$reporter = \PHPSpec\Runner\Reporter::create($result, 'Text');
		$this->assertTrue($reporter instanceof \PHPSpec\Runner\Reporter\Text);
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

