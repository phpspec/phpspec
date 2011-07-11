<?php

namespace PHPSpec;

use \PHPSpec\Runner\Reporter;

class World
{
    protected $options;
    protected $reporter;
    
    public function getOption($name)
    {
        return isset($this->_options[$name]) ? $this->_options[$name] : null;
    }
    
    public function setOption($name, $value)
    {
        if (isset($this->_options[$name])) {
            return $this->_options[$name] = $value;            
        }
        return false;
    }
    
    public function getOptions()
    {
        return $this->_options;
    }
    
    public function setOptions(array $options)
    {
        $this->_options = $options;
    }
    
    public function getReporter()
    {
        return $this->_reporter;
    }
    
    public function setReporter($reporter)
    {
        $this->_reporter = $reporter;
    }
    
    public function loadConfig()
    {
        $ds          = DIRECTORY_SEPARATOR;
        $localConfig =       getcwd() . $ds . '.phpspec';
        $homeConfig  = getenv('HOME') . $ds . '.phpspec';
        $etcConfig   =    $ds . 'etc' . $ds . 'phpspec' . $ds . 'phpspec.conf';
        $configArguments = array();
        
        if (file_exists($localConfig)) {
            $configArguments = file($localConfig);
        } elseif (file_exists($homeConfig)) {
            $configArguments = file($homeConfig);
        } elseif (file_exists($etcConfig)) {
            $configArguments = file($etcConfig);
        }
        
        $this->_options = array_merge($this->_options, $configArguments);
    }
}