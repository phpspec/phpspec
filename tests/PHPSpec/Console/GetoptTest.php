<?php

require_once 'PHPSpec/Console/Getopt.php';

class PHPSpec_Console_GetoptTest extends PHPUnit_Framework_TestCase {
	/**
	 * @test
	 **/
	public function itShouldPrintUsageWhenNoArgumentsAreGiven()
	{
		ob_start();
		$getOpt = new PHPSpec_Console_Getopt(array('/usr/bin/phpspec'));
		$output = ob_get_contents();
		ob_end_clean();
	    $this->assertSame("Usage: phpspec (FILE|DIRECTORY) + [options]
    -c, --colour, --color            Show coloured (red/green) output
    -a, --autospec                   Run all tests continually, every 10 seconds
", $output);
	} 
}