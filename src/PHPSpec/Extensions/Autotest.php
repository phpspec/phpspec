<?php

class PHPSpec_Extensions_Autotest
{
	/**
     * The autotest() static method serves as PHPSpec's Autotester. It will
     * run all tests continually, with 10 second delays between each
     * iterative run and report as normal for each iteration to the console
     * output.
     * 
     * Use the CTRL+C key combination to trigger an exit from the console
     * running loop used for Autotesting.
     *
     * @param PHPSpec_Console_Getopt $options
     */   
	public function run(PHPSpec_Console_Command $command)
	{
		set_time_limit(0);
        
    	if (isset($command->options->a)) {
    		$command->options->a = null;
    	}
        if (isset($command->options->autotest)) {
            $command->options->autotest = null;
        }

    	while(true) {
    	    $command->run($options);
    	    sleep(10);
    	}
	}
}