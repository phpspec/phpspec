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
        $this->formatter = $this->mock('\PHPSpec\Runner\Formatter');
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
        $this->formatter->shouldReceive('setShowColors')->with(true)->times(1);
        return $this;
    }
    
    public function withReporter($reporterExtraMethods='')
    {
        $this->reporter = $this->mock("\PHPSpec\Runner\Cli\Reporter[getFormatter,attach,setRuntimeStart$reporterExtraMethods]");
        $this->reporter->shouldReceive('getFormatter')->andReturn($this->formatter);
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
        $specFile = realpath(__DIR__ . '/Runner/Cli/_files/' . $specFile);
        $this->reporter->shouldReceive('getFormatters')->andReturn(array($this->formatter));
        $this->formatter->shouldReceive('update');
        $this->world->shouldReceive('getOption')->with('specFile')->andReturn($specFile);
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
        if ($this->reporter !== null) {
            $this->world->shouldReceive('getReporter')->andReturn($this->reporter);
        }
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