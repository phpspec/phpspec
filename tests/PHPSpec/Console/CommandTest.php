<?php

class PHPSpec_Console_CommandTest extends PHPUnit_Extensions_OutputTestCase {
    /**
     * @test
     **/
    public function itPrintsUsageItNoOptionsAreGiven()
    {
	    $usage = <<<USAGE
Usage: phpspec (FILE|DIRECTORY) + [options]
    -c, --colour, --color            Show coloured (red/green) output
    -a, --autospec                   Run all tests continually, every 10 seconds

USAGE;
	    chdir(dirname(__FILE__) . '/empty');
		PHPSpec_Console_Command::main(new PHPSpec_Console_Getopt(array(PHPSPEC_BIN)));
		$this->expectOutputString($usage);
		chdir(TESTS_ROOT_DIR);
    }

    /**
     * @test
     **/
    public function itPrintsNoSpecsToExecuteIfAEmptyDirectoryIsGiven()
    {
	    chdir(dirname(__FILE__));
	    PHPSpec_Console_Command::main(new PHPSpec_Console_Getopt(array(PHPSPEC_BIN, 'empty')));
	    $this->expectOutputString('No specs to execute!' . PHP_EOL);
	    chdir(TESTS_ROOT_DIR);
    }

    /**
     * @test
     **/
    public function itUsesTheArgumentListFromCommandLineToCreateGeooptIfNothingIsGiven()
    {
	    chdir(dirname(__FILE__));
	    $tmp = $_SERVER['argv']; 
	    $_SERVER['argv'] = array(PHPSPEC_BIN, 'empty');
		PHPSpec_Console_Command::main();
		$this->expectOutputString('No specs to execute!' . PHP_EOL); 
		chdir(TESTS_ROOT_DIR);
		$_SERVER['argv'] = $tmp;
    }
}