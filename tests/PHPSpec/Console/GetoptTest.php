<?php

require_once 'PHPSpec/Console/Getopt.php';

class PHPSpec_Console_GetoptTest extends PHPUnit_Extensions_OutputTestCase {
	/**
     * @test
     **/
    public function itUsesTheArgumentListFromCommandLineToCreateGeooptIfNothingIsGiven()
    {
	    $tmp = $_SERVER['argv']; 
	    $_SERVER['argv'] = array(PHPSPEC_BIN, 'empty');
		$getopt = new \PHPSpec\Console\Getopt();
		$this->assertSame(
			array(
				'noneGiven' => false, 
				'c' => false,
                'color' => false,
                'colour' => false,
                'a' => false,
                'autotest' => false,
                'h' => false,
                'help' => false,
                'version' => false, 
				'reporter' => 'Console', // Console is the default reporter
			    'specFile' => 'empty'
			),
			$this->readAttribute($getopt, '_options')); 
		$_SERVER['argv'] = $tmp;
     }  
}

array(
                'noneGiven' => false,
                
    );