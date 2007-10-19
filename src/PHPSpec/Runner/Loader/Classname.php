<?php

class PHPSpec_Runner_Loader_Classname
{

    protected $_loaded = array();

    public function load($className)
    {
        $class = $className;
        if (substr($className, strlen($className) - 4, 4) == '.php') {
            $classFile = $className;
            $class = substr($className, 0, strlen($className) - 4);
        } else {
            $classFile = $className . '.php';
        }

        require_once $classFile;

        $classReflected = new ReflectionClass($class);

        $this->_loaded = array($classReflected);

        return $this->_loaded;
    }

    public function getLoaded()
    {
        return $this->_loaded;
    }

}