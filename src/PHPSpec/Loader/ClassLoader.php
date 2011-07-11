<?php

namespace PHPSpec\Loader;

class ClassLoader
{
    protected $_convention;
    protected $_namespace = '';
    
    public function load($fullPath)
    {
        $realPath   = realpath($fullPath);
        $specFile   = basename($realPath);
        $pathToFile = str_replace("/$specFile", '', $realPath);
        $convention = $this->getConventionFactory()->create($specFile);
        
        if ($realPath && !$convention->apply()) {
            return array();
        } elseif (!$realPath) {
            $this->assertFileIsAccessible($fullPath);
        }
        
        return array($this->loadExample(
            $pathToFile . "/" . $convention->getClassFile(),
            $convention->getClass()
        ));
    }
    
    private function loadExample($file, $class)
    {
        $this->assertFileIsAccessible($file);

        $this->includeSpec($file, $class);
        $specClass = new \ReflectionClass($this->_namespace . $class);
        
        $this->_namespace = '';
        $specObject = $specClass->newInstance();
        return $specObject;
    }
    
    private function assertFileIsAccessible($file)
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new \PHPSpec\Runner\Error(
                "Could not include file \"$file\""
            );
        }
    }
    
    private function includeSpec($file, $class)
    {
        require_once $file;
        
        $classes = get_declared_classes();
        foreach ($classes as $declared) {
            if ($this->foundClass($declared, $class)) {
                return true;
            }
        }
        throw new \PHPSpec\Runner\Error(
            "Could not find class \"$class\" in file \"$file\""
        );
    }
    
    private function foundClass($declared, $class)
    {
        return $this->declaredContainsClassName($declared, $class)
               && ($this->declaredAndClassNamesAreTheSame($declared, $class)
               || $this->differenceIsANamespace($declared, $class));
    }
    
    private function declaredContainsClassName($declared, $class)
    {
        return strpos($declared, $class) !== false;
    }
    
    private function declaredAndClassNamesAreTheSame($declared, $class)
    {
        return $declared === $class;
    }
    
    private function differenceIsANamespace($declared, $class)
    {
        $differenceIsANamespace = substr(
            $declared, 0 - strlen($class)
        ) === $class;
        if ($differenceIsANamespace) {
            $this->_namespace = $this->extractNamespace(
                $declared, $class
            );
        }
        return $differenceIsANamespace;
    }
    
    private function extractNamespace($declared, $class)
    {
        return substr($declared, 0, strlen($declared) - strlen($class));
    }
    
    public function getConventionFactory()
    {
        if ($this->_convention === null) {
            $this->_convention = new ConventionFactory();
        }
        return $this->_convention;
    }
    
    public function setConventionFactory(ConventionFactory $convention)
    {
        $this->_convention = $convention;
    }
}
