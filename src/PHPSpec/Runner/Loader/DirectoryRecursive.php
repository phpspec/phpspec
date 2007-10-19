<?php

class PHPSpec_Runner_Loader_DirectoryRecursive
{

    protected $_loaded = array();

    public function load($directory)
    {
        $dirpath = new DirectoryIterator($directory);
        foreach($dirpath as $resource) {
            if () {
            
            }
        }


        /**$class = $className;
        if (substr($className, strlen($className) - 4, 4) == '.php') {
            $classFile = $className;
            $class = substr($className, 0, strlen($className) - 4);
        } else {
            $classFile = $className . '.php';
        }

        require_once $classFile;

        $classReflected = new ReflectionClass($class);

        $this->_loaded = array($classReflected);

        return $this->_loaded;*/
    }

    public function getLoaded()
    {
        return $this->_loaded;
    }

    protected function compileRunnableBehaviours($dir)
    {
        $recursiveDir = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        foreach($recursiveDir as $file) {
            echo $file, PHP_EOL;
        }   
    }

}