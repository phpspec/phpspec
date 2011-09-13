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
    function before()
    {
        $this->runner = $this->spec(new CliRunner);
    }
    
    function itHaltsTheRunAndSetsVersionMessageIfVersionOptionIsSet()
    {
        $worldBuilder = new WorldBuilder;
        
        $world = $worldBuilder->withVersion()
                              ->build();
                              
        $worldBuilder->getReporter()->shouldReceive('setMessage')
                                    ->with(CliRunner::VERSION);

        $this->runner->run($world);
    }
    
    function itHaltsTheRunAndSetsHelpMessageIfHelpOptionIsSet()
    {
        $worldBuilder = new WorldBuilder;
        
        $world = $worldBuilder->withHelp()
                              ->build();

        $worldBuilder->getReporter()->shouldReceive('setMessage')
                                    ->with(CliRunner::USAGE);

        $this->runner->run($world);
    }
    
    function itSetsTheFormatterToDisplayColours()
    {
        $worldBuilder = new WorldBuilder;
        $world = $worldBuilder->withColours()
                              ->withSpecFile('FooSpec.php')
                              ->build();
        
        $worldBuilder->getFormatter()->shouldReceive('setShowColors')
                                     ->with(true)->once();
        
        
        $this->runner->run($world);
    }
    
    function itSetsTheFormatterToDisplayBacktrace()
    {
        $worldBuilder = new WorldBuilder;
        $world = $worldBuilder->withBacktrace()
                              ->withSpecFile('FooSpec.php')
                              ->build();
                              
        $worldBuilder->getFormatter()->shouldReceive('setEnableBacktrace')
                                     ->with(true)->once();
        
        $this->runner->run($world);
    }
    
    function itTellsTheReporterToFailFast()
    {
        $worldBuilder = new WorldBuilder;
        
        $world = $worldBuilder->withSpecFile('FooSpec.php')
                              ->withFailFast()
                              ->build();
                              
        $worldBuilder->getReporter()->shouldReceive('setFailFast')
                                    ->with(true)->once();
        
        $this->runner->run($world);
    }
    
    function itSetsTheExampleToBeRunIntoTheRunner()
    {
        $worldBuilder = new WorldBuilder;
        $world = $worldBuilder->withExample('itDoesSomething')
                              ->withSpecFile('FooSpec.php')
                              ->build();
        $this->runner->setExampleRunner($worldBuilder->exampleRunner);
        
        $worldBuilder->exampleRunner->shouldReceive('runOnly')
                                    ->with('itDoesSomething')->once();
        
        $this->runner->run($world);
    }
    
    function itSetsTheErrorHandler()
    {
        $worldBuilder = new WorldBuilder;
        $world = $worldBuilder->withSpecFile('SomethingSpec.php')
                              ->withErrorHandler()
                              ->build();
                              
        $error = $this->mock('\SomeErrorHandler');
        $error->shouldReceive('someMethod')->times(1);
        $this->runner->setErrorHandler(array($error, 'someMethod'));
        
        $this->runner->run($world);
    }
}
