<?php

/**
 * Code based on PHPT by Travis Swicegood, licensed under the LGPL
 */

class PHPSpec_Console_Getopt
{

    protected $_options = array();

    public function __construct(array $argv = null)
    {
        if (is_null($argv)) {
            $argv = $_SERVER['argv'];
        }
        $this->_parse($argv);
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

    public function hasOption($name)
    {
        return isset($this->_options[$name]);
    }

    protected function __isset($name)
    {
        return $this->hasOption($name);
    }

    public function getAllOptions()
    {
        return $this->_options;
    }

    public static function parseOptionString($str)
    {
        $argv = explode(' ', $str);
        $options = new self($argv);
        return $options;
    }

    protected function _parse(array $argv)
    {
        // get rid of the Command.php reference
        if (is_file($argv[0])) {
            array_shift($argv);
        }

        // if the first argument is not a - or -- option it should be a spec filename
        if ($argv[0][0] !== '-') {
            $this->_options['specFile'] = $argv[0];
            array_shift($argv);
        }

        $encountered = null;
        foreach ($argv as $value) {
            if (!is_null($encountered)) {
                if ($value[0] != '-') {
                    $this->_options[$encountered] = $value;
                    $encountered = null;
                    continue;
                } else {
                    $this->_options[$encountered] = true;
                }
            }
            if (substr($value, 0, 2) == '--') {
                $encountered = substr($value, 2);
                continue;
            } else {
                $values = str_split(substr($value, 1));
                foreach ($values as $letter) {
                    $this->_options[$letter] = true;
                }
            }
        }
        if (!is_null($encountered)) {
            $this->_options[$encountered] = true;
        }
    }

}