<?php

namespace Spec\PHPSpec\Runner\Cli;

require_once __DIR__ . '/../../SpecHelper.php';

use \PHPSpec\Runner\Cli\Runner as CliRunner;

class DescribeRunner extends \PHPSpec\Context
{
    function before()
    {
        $this->runner = $this->spec(new CliRunner);
    }
    
    function itSetsTheReporterToPrintVersionIfVersionOptionIsSet()
    {
        // $world = $this->mock('\PHPSpec\World');
        // $world->stub('getVersion')->andReturn(true);
        // 
        // $reporter = $this->mock('\PHPSpec\Runner\Cli\Reporter');
        // $reporter->stub('setMessage')->shouldReceive(CliRunner::VERSION)
        //          ->exactly(1);
        // $formatter = $this->stub('\SplObserver');
        //         $reporter->stub('attach')->shouldReceive($formatter);
        //                  
        //         $world->setReporter($reporter);
        //         
        //         $this->runner->run($world);
    }
    
    function itLoadsBootstrapFileIfSpecified() {
    	
        $tmp_dir = sys_get_temp_dir();
    	$tmpfname = tempnam("/tmp", "phpspec_bootstrap.php");
        $str_bootstrap = '<?php class BootstrapTester {}';
        file_put_contents($tmpfname, $str_bootstrap);
        
        $spec_file = tempnam("/tmp", "SpecFake.php");
        $str_spec = '<?php class DescriveFake extends \PHPSpec\Context {}';
        file_put_contents($spec_file, $str_spec);
        
    	$reporter = $this->mock('\PHPSpec\Runner\Cli\Reporter');
    	$reporter->shouldReceive('setMessage')->andReturn(CliRunner::VERSION);
        
        $formatter = $this->mock('\SplObserver');
        $reporter->shouldReceive('attach')->andReturn($formatter);
        $reporter->shouldReceive('getFormatter')->andReturn($formatter);
        $reporter->shouldReceive('setRuntimeStart');
        $reporter->shouldReceive('setRuntimeEnd');
    	
    	$world = $this->mock('\PHPSpec\World');
    	$world->shouldReceive('getOption')->with('bootstrap')->andReturn($tmpfname);
    	$world->shouldReceive('getOption')->with('specFile')->andReturn($spec_file);
    	$world->shouldReceive('getOption')->andReturn();
    	$world->shouldReceive('getReporter')->andReturn($reporter);
    	
    	$this->runner->run($world);
    	
    	$this->spec(class_exists('BootstrapTester'))->should->beTrue();
    	
    	unlink($tmpfname);
    	unlink($spec_file);
    }
}