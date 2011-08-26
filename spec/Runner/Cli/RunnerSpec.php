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
    
    function itWillHaltTheRunAndSetVersionMessageIfVersionOptionIsSet()
    {
        $reporter = $this->mock('\PHPSpec\Runner\Reporter');
        $reporter->shouldReceive('setMessage')->with(CliRunner::VERSION);
        
        $world = $this->mock('\PHPSpec\World');
        $world->shouldReceive('getOption')->with('version')->andReturn(true);
        $world->shouldReceive('getReporter')->andReturn($reporter);

        $this->runner->run($world)->should->beNull();
    }
    
    function itWillHaltTheRunAndSetHelpMessageIfHelpOptionIsSet()
    {
        $reporter = $this->mock('\PHPSpec\Runner\Reporter');
        $reporter->shouldReceive('setMessage')->with(CliRunner::USAGE);
        
        $world = $this->mock('\PHPSpec\World');
        $world->shouldReceive('getOption')->with('version')->andReturn(false);
        $world->shouldReceive('getOption')->with('h')->andReturn(true);
        $world->shouldReceive('getReporter')->andReturn($reporter);

        $this->runner->run($world)->should->beNull();
    }
}