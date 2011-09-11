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
        $this->world = $this->mock('\PHPSpec\World');
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
        list ($this->formatter, $this->world) = $this->setupOptions(array(
            'show' => array('c'),
            'dont show' => array('version', 'h', 'help', 'b', 'failfast', 'example', 'specFile')
            )
        );
        $this->formatter->shouldReceive('setShowColors')->with(true)->times(1);
        return $this;
    }
    
    public function withReporterAndMessage($message)
    {
        $this->reporter = $this->mock('\PHPSpec\Runner\Reporter');
        $this->reporter->shouldReceive('setMessage')->with($message);
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