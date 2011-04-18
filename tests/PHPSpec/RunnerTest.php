<?php

class RunnerTest extends \PHPUnit_Extensions_OutputTestCase {
	/**
     * @test
     **/
    public function itPrintsNoSpecsToExecuteIfAEmptyDirectoryIsGiven()
    {
	    \PHPSpec\Runner::run(new stdClass);
	    $this->expectOutputString('No specs to execute!' . PHP_EOL);
    }
}