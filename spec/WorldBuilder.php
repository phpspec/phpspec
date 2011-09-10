<?php

namespace Spec\PHPSpec;

class WorldBuilder
{
    private $version = false;
    private $help = false;
    private $reporter;
    
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
    
    public function withReporterAndMessage($message)
    {
        $this->reporter = $this->mock('\PHPSpec\Runner\Reporter');
        $this->reporter->shouldReceive('setMessage')->with($message);
        return $this;
    }
    
    public function build()
    {
        $world = $this->mock('\PHPSpec\World');
        $this->setVersionAndHelp($world, $this->version, $this->help)
             ->setReporter($world);
        return $world;
    }
    
    private function setVersionAndHelp($world, $version, $help)
    {
        $world->shouldReceive('getOption')->with('version')->andReturn($version);
        $world->shouldReceive('getOption')->with('h')->andReturn($help);
        $world->shouldReceive('getOption')->with('help')->andReturn($help);
        return $this;
    }
    
    private function setReporter($world)
    {
        if ($this->reporter !== null) {
            $world->shouldReceive('getReporter')->andReturn($this->reporter);
        }
        return $this;
    }
    
    private function mock($class)
    {
        return \Mockery::mock($class);
    }

}