<?php

class PHPSpec_Console_CommandTest extends \PHPUnit_Extensions_OutputTestCase {
      
    /** @test */
    public function itCreatesAGetoptIfNoneIsGiven() {
        $command = new \PHPSpec\Console\Command;
        $this->assertTrue($this->readAttribute($command, '_options') instanceof \PHPSpec\Console\Getopt);
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

    /** @test */
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

    /** @test */
    public function itCallsTheAutotestIfTheOptionIsPassed()
    {
        $_SERVER['argv'] = array('phpspec', '--autotest');
        $command = new \PHPSpec\Console\Command();
        
        $autotest = $this->getMock("\\PHPSpec\\Extensions\\Autotest");
        $autotest->expects($this->once())
                 ->method('run');

        $command->setAutotest($autotest);
        $command->run();
    }

    /** @test */
    public function itCallsTheAutotestIfTheShortOptionIsPassed()
    {
        $_SERVER['argv'] = array('phpspec', '-a');
        $command = new \PHPSpec\Console\Command();
        
        $autotest = $this->getMock("\\PHPSpec\\Extensions\\Autotest");
        $autotest->expects($this->once())
                 ->method('run');

        $command->setAutotest($autotest);
        $command->run();
    }

    /** @test */
    public function itPrintsTheCurrentVersion()
    {
        $_SERVER['argv'] = array('phpspec', '--version');
        $command = new \PHPSpec\Console\Command();
        
        $command->run();
        $this->expectOutputString(\PHPSpec\Framework::VERSION . PHP_EOL);
    }

    /** @test */
    public function itPrintsTheUsageWithTheHelpSwitch()
    {
        $_SERVER['argv'] = array('phpspec', '--help');
        $command = new \PHPSpec\Console\Command();
        
        $command->run();
        $this->expectOutputString(\PHPSpec\Console\Command::USAGE);
    }

    /** @test */
    public function itPrintsTheUsageWithTheShortHelpSwitch()
    {
        $_SERVER['argv'] = array('phpspec', '-h');
        $command = new \PHPSpec\Console\Command();
        
        $command->run();
        $this->expectOutputString(\PHPSpec\Console\Command::USAGE);
    }
    
    /** @test */
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