<?php

class PHPSpec_Console_CommandTest extends PHPUnit_Extensions_OutputTestCase {
      
	/** @test */
	public function itCreatesAGetoptIfNoneIsGiven() {
		$command = new PHPSpec_Console_Command;
		$this->assertTrue($this->readAttribute($command, 'options') instanceof PHPSpec_Console_Getopt);
	}
	
	/** @test */
	public function itCreatesAAutotestIfNoneIsGiven() {
		$command = new PHPSpec_Console_Command;
		$this->assertTrue($command->getAutotest() instanceof PHPSpec_Extensions_Autotest);
	}
	
	/** @test */
	public function itCreatesARunnerIfNoneIsGiven() {
		$command = new PHPSpec_Console_Command;
		$this->assertTrue($command->getRunner() instanceof PHPSpec_Runner);
	} 

    /**
	 * @test
	 **/
	public function itPrintsUsageWhenGetoptIsMarkedWithExit()
	{
		$getopt = $this->getMock('PHPSpec_Console_Getopt');
		$getopt->expects($this->once())
		       ->method('noneGiven')
			   ->will($this->returnValue(true));
		$command = new PHPSpec_Console_Command($getopt);
		
		$command->run();
		
	    $this->expectOutputString(PHPSpec_Console_Command::USAGE);
	}

	/**
	 * @test
	 */
	public function itCallsTheAutotestIfTheOptionIsPassed()
	{
		$getopt = $this->getMock('PHPSpec_Console_Getopt');
		$getopt->expects($this->once())
			->method('getOption')
			->with('a')
			->will($this->returnValue(true));
		$command = new PHPSpec_Console_Command($getopt);
		
		$autotest = $this->getMock('PHPSpec_Extensions_Autotest');
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
		$getopt = $this->getMock('PHPSpec_Console_Getopt');
		$getopt->expects($this->once())
		       ->method('noneGiven')
			   ->will($this->returnValue(false));
		$command = new PHPSpec_Console_Command($getopt);

		$runner = $this->getMock('PHPSpec_Runner');
		$runner->expects($this->once())
			   ->method('run')
			   ->with($getopt);
		$command->setRunner($runner);
		
		$command->run(); 
	}
}