<?php

class PHPSpec_Runner_Loader_DirectoryRecursive
{

    protected $_loaded = array();

    protected $_loadedClasses = array();

    protected $_filter = null;

    protected $_filterName = 'PHPSpec_Runner_Filter_Standard';

    protected $_directory = null;

    public function load($directory)
    {
        $this->_directory = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $filterIterator = $this->getFilter();

        foreach ($filterIterator as $file) {
            $pathName = $file->getPathname();
            require_once $pathName;
            
            $fileName = $file->getFilename();
            $className = substr($fileName, 0, strlen($fileName) - 4);
            if (class_exists($className, false) && !in_array($className, $this->_loadedClasses)) {
                $classReflected = new ReflectionClass($className);
                $this->_loaded[] = $classReflected;
            }
        }

        return $this->_loaded;
    }

    public function getLoaded()
    {
        return $this->_loaded;
    }

    public function setFilterClass($filterName)
    {
        $this->_filterName = $filterName;
    }

    public function getFilter()
    {
        if (is_null($this->_filter)) {
            if (is_null($this->_directory)) {
                throw new Exception();
            }
            $reflection = new ReflectionClass($this->_filterName);
            $this->_filter = $reflection->newInstance($this->_directory);
        }
        return $this->_filter;
    }

}