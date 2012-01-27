<?php

namespace Spec\PHPSpec;

class WorldBuilder
{
    private $version = false;
    private $help = false;
    private $reporter;
    private $world;
    private $formatter;
    
    public function __construct()
    {
        $this->world = $this->mock('\PHPSpec\World[getReporter,getOption]');
        $this->world->shouldReceive('getOption')->with('bootstrap')->andReturn(null);
        $this->formatter = $this->mock('\PHPSpec\Runner\Formatter');
    }
    
    public function getFormatter()
    {
        return $this->formatter;
    }
    
    public function getReporter()
    {
        return $this->reporter;
    }
    
    public function withVersion()
    {
        $this->version = true;
        return $this;
    }
    
    public function withHelp()
    {
        $this->help = true;
        return $this;
    }
    
    public function withColours()
    {
        $this->setupOptions(array(
            'show' => array('c'),
            'dont show' => array('version', 'h', 'help', 'b', 'failfast', 'example')
            )
        );
        return $this;
    }
    
    public function withReporter($reporterExtraMethods='')
    {
        $this->reporter = $this->mock("\PHPSpec\Runner\Cli\Reporter[getFormatters,attach,setRuntimeStart$reporterExtraMethods]");
        $this->reporter->shouldReceive('getFormatters')->andReturn(array($this->formatter));
        $this->reporter->shouldReceive('attach')->with($this->formatter);
        $this->reporter->shouldReceive('setRuntimeStart');
        return $this;
    }
    
    public function withReporterAndMessage($message)
    {
        $this->reporter = $this->mock('\PHPSpec\Runner\Reporter');
        $this->reporter->shouldReceive('setMessage')->with($message);
        return $this;
    }
    
    public function withSpecFile($specFile)
    {
        $this->withReporter(',getFormatters');
        $specFile = realpath(__DIR__ . '/Runner/Cli/_files/' . $specFile);
        $this->reporter->shouldReceive('getFormatters')->andReturn(array($this->formatter));
        $this->formatter->shouldReceive('update');
        $this->world->shouldReceive('getOption')->with('specFile')->andReturn($specFile);
        return $this;
    }
    
    public function withBacktrace()
    {
        $this->setupOptions(array(
            'show' => array('b'),
            'dont show' => array('version', 'h', 'help', 'c', 'failfast', 'example')
            )
        );
        return $this;
    }
    
    public function withFailFast()
    {
        $this->withReporter(',setFailFast,getFormatters');
        $this->setupOptions(array(
            'show' => array('failfast'),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'example')
            )
        );
        $this->reporter->shouldReceive('getFormatters')->andReturn(array($this->formatter));
        return $this;
    }
    
    public function withExample($example)
    {
        $this->withReporter(',getFormatters');
        $this->setupOptions(array(
            'show' => array(),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'failfast')
            )
        );
        $this->world->shouldReceive('getOption')->with('example')->andReturn($example);
        $this->exampleRunner = $this->mock('\PHPSpec\Specification\ExampleRunner[runOnly]');
        return $this;
    }
    
    public function withErrorHandler()
    {
        $this->withNoOptions();
        return $this;
    }
    
    public function withNoOptions()
    {
        $this->withReporter(',getExceptions');
        $this->setupOptions(array(
            'show' => array(),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'failfast', 'example')
            )
        );
        $this->reporter->shouldReceive('getExceptions')
                       ->andReturn(new \SplObjectStorage);
                       
        return $this;
    }
    
    public function withNoOptionsAndSpecFile($specFile)
    {
        $this->withReporter(',getFormatters,getErrors');
        $this->setupOptions(array(
            'show' => array(),
            'dont show' => array('version', 'h', 'help', 'c', 'b', 'failfast', 'example')
            )
        );
        $specFile = realpath($specFile);
        $this->reporter->shouldReceive('getFormatters')->andReturn(array($this->formatter));
        $this->formatter->shouldReceive('update');
        $this->world->shouldReceive('getOption')->with('specFile')->andReturn($specFile);
        $this->reporter->shouldReceive('getErrors')
                       ->andReturn(new \SplObjectStorage);
                       
        return $this;
    }
    
    public function build()
    {
        $this->setVersionAndHelp($this->version, $this->help)
             ->setReporter();
        return $this->world;
    }
    
    private function setVersionAndHelp($version, $help)
    {
        $this->world->shouldReceive('getOption')->with('version')->andReturn($version);
        $this->world->shouldReceive('getOption')->with('h')->andReturn($help);
        $this->world->shouldReceive('getOption')->with('help')->andReturn($help);
        return $this;
    }
    
    private function setReporter()
    {
        if ($this->reporter === null) {
            $this->reporter = $this->mock('\PHPSpec\Runner\Reporter');
        }
        $this->world->shouldReceive('getReporter')->andReturn($this->reporter);
        return $this;
    }
    
    private function mock($class)
    {
        return \Mockery::mock($class);
    }

    private function setupOptions($options, $reporterExtraMethods = '')
    {        
        $this->setOptionsAsFalse($this->world, $options['dont show']);
        $this->setOptionsAsTrue($this->world, $options['show']);
    }
    
    private function setOptionsAsFalse($world, $options)
    {
        foreach ($options as $option) {
            $world->shouldReceive('getOption')->with($option)->andReturn(false);
        }
    }
    
    private function setOptionsAsTrue($world, $options)
    {
        foreach ($options as $option) {
            $world->shouldReceive('getOption')->with($option)->andReturn(true);
        }
    }
}