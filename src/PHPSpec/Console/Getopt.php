<?php

class PHPSpec_Console_Getopt
{

    protected $_argv = array();

    protected $_options = array();

    public function __construct(array $argv = null)
    {
        if (is_null($argv)) {
            $this->_argv = $_SERVER['argv'];
        } else {
            $this->_argv = $argv;
        }
        $this->_options['specFile'] = $this->_argv[1];
    }

    public function getOption($name)
    {
        return $this->_options[$name];
    }

    protected function __get($name)
    {
        return $this->getOption($name);
    }

    public function setOption($name, $value)
    {
        $this->_options[$name] = $value;
    }

    protected function __set($name, $value)
    {
        $this->setOption($name, $value);
    }

}