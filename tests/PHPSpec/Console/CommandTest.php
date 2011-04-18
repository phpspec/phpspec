<?php

class PHPSpec_Console_CommandTest extends \PHPUnit_Extensions_OutputTestCase {
      
	/** @test */
	public function itCreatesAGetoptIfNoneIsGiven() {
		$command = new \PHPSpec\Console\Command;
		$this->assertTrue($this->readAttribute($command, 'options') instanceof \PHPSpec\Console\Getopt);
	}
	
	/** @test */
	public function itCreatesAAutotestIfNoneIsGiven() {
		$command = new \PHPSpec\Console\Command;
		$this->assertTrue($command->getAutotest() instanceof \PHPSpec\Extensions\Autotest);
	}
	
	/** @test */
	public function itCreatesARunnerIfNoneIsGiven() {
		$command = new \PHPSpec\Console\Command;
		$this->assertTrue($command->getRunner() instanceof \PHPSpec\Runner);
	} 

    /**
	 * @test
	 **/
	public function itPrintsUsageWhenGetoptIsMarkedWithExit()
	{
		$getopt = $this->getMock("\\PHPSpec\\Console\\Getopt");
		$getopt->expects($this->once())
		       ->method('noneGiven')
			   ->will($this->returnValue(true));
		$command = new \PHPSpec\Console\Command($getopt);
		
		$command->run();
		
	    $this->expectOutputString(\PHPSpec\Console\Command::USAGE);
	}

	/**
	 * @test
	 */
	public function itCallsTheAutotestIfTheOptionIsPassed()
	{
		$getopt = $this->getMock("\\PHPSpec\\Console\\Getopt");
		$getopt->expects($this->once())
			->method('getOption')
			->with('a')
			->will($this->returnValue(true));
		$command = new \PHPSpec\Console\Command($getopt);
		
		$autotest = $this->getMock("\\PHPSpec\\Extensions\\Autotest");
		$autotest->expects($this->once())
		         ->method('run');

		$command->setAutotest($autotest);
		$command->run();
	}
	
	/**
	 * @test
	 */
	public function itRunsIfOneOptionsIsGiven()
	{
		$getopt = $this->getMock("\\PHPSpec\\Console\\Getopt");
		$getopt->expects($this->once())
		       ->method('noneGiven')
			   ->will($this->returnValue(false));
		$command = new \PHPSpec\Console\Command($getopt);

		$runner = $this->getMock("\\PHPSpec\Runner");
		$runner->expects($this->once())
			   ->method('run')
			   ->with($getopt);
		$command->setRunner($runner);
		
		$command->run(); 
	}
}