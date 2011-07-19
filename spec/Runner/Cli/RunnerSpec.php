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
}