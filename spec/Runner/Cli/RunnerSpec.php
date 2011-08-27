<?php

namespace Spec\PHPSpec\Runner\Cli;

require_once __DIR__ . '/../../SpecHelper.php';
require_once '/md/dev/php/phpspec/src/PHPSpec/Runner/Reporter.php';
require_once '/md/dev/php/phpspec/src/PHPSpec/Runner/Cli/Reporter.php';

use \PHPSpec\Runner\Cli\Runner as CliRunner;

class DescribeRunner extends \PHPSpec\Context
{
    function itWillHaltTheRunAndSetVersionMessageIfVersionOptionIsSet()
    {
        $reporter = $this->getReporterStubWithMessage(CliRunner::VERSION);
        $world = $this->getWorldStub(self::SHOW_VERSION);
        $world->shouldReceive('getReporter')->andReturn($reporter);

        $this->runner->run($world)->should->beNull();
    }
    
    function itWillHaltTheRunAndSetHelpMessageIfHelpOptionIsSet()
    {
        $reporter = $this->getReporterStubWithMessage(CliRunner::USAGE);
        $world = $this->getWorldStub(self::DONT_SHOW_VERSION, self::SHOW_HELP);
        $world->shouldReceive('getReporter')->andReturn($reporter);

        $this->runner->run($world)->should->beNull();
    }
    
    function itWillSetTheFormatterToDisplayColours()
    {
        // set up
        list ($formatter, $world) = $this->setupOptions(
            array('c'),
            array('version', 'h', 'help', 'b', 'failfast', 'example', 'specFile')
        );
        
        // expectation:
        $formatter->shouldReceive('setShowColors')->with(true)->times(1);
        
        // exercise
        try {
            $this->runner->run($world);
        } catch (\PHPSpec\Runner\Cli\Error $e) {
            // because I am setting no spec file
        }
    }
    
    function before()
    {
        $this->runner = $this->spec(new CliRunner);
    }
    
    function setupOptions($show, $dontShow)
    {
        $formatter = $this->mock('\PHPSpec\Runner\Formatter');
        $reporter = $this->mock('\PHPSpec\Runner\Cli\Reporter[getFormatter,attach,setRuntimeStart]');
        $reporter->shouldReceive('getFormatter')->andReturn($formatter);
        $reporter->shouldReceive('attach')->with($formatter);
        $reporter->shouldReceive('setRuntimeStart');
        $world = $this->mock('\PHPSpec\World[getReporter,getOption]');
        $world->shouldReceive('getReporter')->andReturn($reporter);        
        $this->setOptionsAsFalse($world, $dontShow);
        $this->setOptionsAsTrue($world, $show);
        return array($formatter, $world);
    }
    
    function setOptionsAsFalse($world, $options)
    {
        foreach($options as $option) {
            $world->shouldReceive('getOption')->with($option)->andReturn(false);
        }
    }
    
    function setOptionsAsTrue($world, $options)
    {
        foreach($options as $option) {
            $world->shouldReceive('getOption')->with($option)->andReturn(true);
        }
    }
    
    function getReporterStubWithMessage($message)
    {
        $reporter = $this->mock('\PHPSpec\Runner\Reporter');
        $reporter->shouldReceive('setMessage')->with($message);
        return $reporter;
    }
    
    function getWorldStub($version = false, $help = false)
    {
        $world = $this->mock('\PHPSpec\World');
        $this->setVersionAndHelp($world, $version, $help);
        return $world;
    }
    
    function setVersionAndHelp($world, $version, $help)
    {
        $world->shouldReceive('getOption')->with('version')->andReturn($version);
        $world->shouldReceive('getOption')->with('h')->andReturn($help);
        $world->shouldReceive('getOption')->with('help')->andReturn($help);
    }
    
    const SHOW_HELP = true, SHOW_VERSION = true, DONT_SHOW_HELP = false,
          DONT_SHOW_VERSION = false;
}
