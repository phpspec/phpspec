<?php

namespace Spec\PHPSpec\Runner\Cli;

require_once __DIR__ . '/../../SpecHelper.php';
require_once __DIR__ . '/../../WorldBuilder.php';
require_once '/md/dev/php/phpspec/src/PHPSpec/Runner/Reporter.php';
require_once '/md/dev/php/phpspec/src/PHPSpec/Runner/Cli/Reporter.php';

use \PHPSpec\Runner\Cli\Runner as CliRunner,
    \Spec\PHPSpec\WorldBuilder;

class DescribeRunner extends \PHPSpec\Context
{
    function itHaltsTheRunAndSetsVersionMessageIfVersionOptionIsSet()
    {
        $worldBuilder = new WorldBuilder;
        
        $world = $worldBuilder->withVersion()
                              ->withReporterAndMessage(CliRunner::VERSION)
                              ->build();

        $this->runner->run($world);
    }
    
    function itHaltsTheRunAndSetsHelpMessageIfHelpOptionIsSet()
    {
        $worldBuilder = new WorldBuilder;
        
        $world = $worldBuilder->withHelp()
                              ->withReporterAndMessage(CliRunner::USAGE)
                              ->build();

        $this->runner->run($world);
    }
    
    function itSetsTheFormatterToDisplayColours()
    {
        $worldBuilder = new WorldBuilder;
        $world = $worldBuilder->withColours()
                              ->build();
        
        try {
            $this->runner->run($world);
        } catch (\PHPSpec\Runner\Cli\Error $e) {
            
        }
    }
    
    function itSetsTheFormatterToDisplayBacktrace()
    {
        list ($formatter, $world) = $this->setupOptions(array(
            'show' => array('b'),
            'dont show' => array('version', 'h', 'help', 'c', 'failfast', 'example', 'specFile')
            )
        );
        
        $formatter->shouldReceive('setEnableBacktrace')->with(true)->times(1);
        
        try {
            $this->runner->run($world);
        } catch (\PHPSpec\Runner\Cli\Error $e) {
            
        }
    }
    
    function itTellsTheReporterToFailFast()
    {
        $reporterExtraMethod = ',setFailFast';
        list (, $world, $reporter) = $this->setupOptions(array(
            'show' => array('failfast'),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'example', 'specFile')
            ),
            $reporterExtraMethod
        );
        $reporter->shouldReceive('setFailFast')->with(true)->times(1);
        
        try {
            $this->runner->run($world);
        } catch (\PHPSpec\Runner\Cli\Error $e) {
            
        }
    }
    
    function itSetsTheExampleToBeRunIntoTheRunner()
    {
        list (, $world) = $this->setupOptions(array(
            'show' => array(),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'failfast', 'specFile')
            )
        );
        $world->shouldReceive('getOption')->with('example')->andReturn('foo');
        $exampleRunner = $this->mock('\PHPSpec\Specification\ExampleRunner');
        $this->runner->setExampleRunner($exampleRunner);
        
        $exampleRunner->shouldReceive('runOnly')->with('foo')->times(1);
        
        try {
            $this->runner->run($world);
        } catch (\PHPSpec\Runner\Cli\Error $e) {
            
        }
    }
    
    function itSetsTheErrorHandler()
    {
        $reporterExtraMethod = ',getExceptions';
        list (, $world, $reporter) = $this->setupOptions(array(
            'show' => array(),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'failfast', 'example')
            ),
            $reporterExtraMethod
        );
        $reporter->shouldReceive('getExceptions')->andReturn(new \SplObjectStorage);
        $error = $this->mock('\SomeErrorHandler');
        $error->shouldReceive('someMethod')->times(1);
        $world->shouldReceive('getOption')->with('specFile')->andReturn('SomethingSpec.php');
        
        $this->runner->setErrorHandler(array($error, 'someMethod'));
        
        $spec = <<<SPEC
<?php
class DescribeSomething extends \PHPSpec\Context
{
    function itTriggersSomeError()
    {
        error_reporting(E_ALL);
        trigger_error('Some error');
    }
}
SPEC;
        $getcdir = getcwd();
        chdir(__DIR__);
        @unlink(__DIR__ . '/SomethingSpec.php');
        file_put_contents(__DIR__ . '/SomethingSpec.php', $spec);
        
        $this->runner->run($world);
        
        unlink(__DIR__ . '/SomethingSpec.php');
        chdir($getcdir);
    }
    
    function before()
    {
        $this->runner = $this->spec(new CliRunner);
    }
    
    function setupOptions($options, $reporterExtraMethods = '')
    {
        $formatter = $this->mock('\PHPSpec\Runner\Formatter');
        $reporter = $this->mock("\PHPSpec\Runner\Cli\Reporter[getFormatter,attach,setRuntimeStart$reporterExtraMethods]");
        $reporter->shouldReceive('getFormatter')->andReturn($formatter);
        $reporter->shouldReceive('attach')->with($formatter);
        $reporter->shouldReceive('setRuntimeStart');
        $world = $this->mock('\PHPSpec\World[getReporter,getOption]');
        $world->shouldReceive('getReporter')->andReturn($reporter);        
        $this->setOptionsAsFalse($world, $options['dont show']);
        $this->setOptionsAsTrue($world, $options['show']);
        return array($formatter, $world, $reporter);
    }
    
    function setOptionsAsFalse($world, $options)
    {
        foreach ($options as $option) {
            $world->shouldReceive('getOption')->with($option)->andReturn(false);
        }
    }
    
    function setOptionsAsTrue($world, $options)
    {
        foreach ($options as $option) {
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
