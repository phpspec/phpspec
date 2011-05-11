<?php

class PHPSpec_Console_CommandTest extends \PHPUnit_Extensions_OutputTestCase {

    /** @test */
    public function itPrintsAnErrorWhenNoArgumentsAreGiven()
    {
        $this->setExpectedException(
            '\PHPSpec\Console\Exception',
            'No arguments given. Type phpspec -h for help'
        );
        $command = new \PHPSpec\Console\Command;
        $command->run();
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
        $_SERVER['argv'] = array('phpspec', '-c');
        $command = new \PHPSpec\Console\Command();

        $runner = $this->getMock("\\PHPSpec\\Runner");
        $runner->expects($this->once())
               ->method('run');
        $command->setRunner($runner);
        
        $command->run(); 
    }
}