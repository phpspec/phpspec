<?php

class PHPSpec_Runner
{

    public static function run($options)
    {
        if (empty($options) || (!is_array($options) && !$options instanceof PHPSpec_Console_Getopt && !$options instanceof stdClass)) {
            throw new PHPSpec_Exception('PHPSpec_Runner received no options!');
        }
        if (is_array($options)) {
            $options = $this->_toObject($options);
        }

        $runnable = array();
        $generateSpecdox = false;
        
        // check for straight class to execute
        if (isset($options->specFile)) {
            $loader = new PHPSpec_Runner_Loader_Classname;
            $runnable += $loader->load($options->specFile);
        }
    
        // should only recurse if not running a single spec
        if ((isset($options->r) || isset($options->recursive)) && !isset($options->specFile)) {
            $loader = new PHPSpec_Runner_Loader_DirectoryRecursive;
            $runnable += $loader->load( getcwd() );
        }

        if (isset($options->s) || isset($options->specdoc)) {
            $generateSpecdox = true;
        }

        if (empty($runnable)) {
            echo 'No specs to execute!';
            return;
        }

        $result = new PHPSpec_Runner_Result;
        $result->setRuntimeStart(microtime(true));
        
        if (isset($options->reporter)) {
        	$reporterClass = 'PHPSpec_Runner_Reporter_' . ucfirst($options->reporter);
        } else {
        	$reporterClass = 'PHPSpec_Runner_Reporter_Text';
        }        
        $reporter = new $reporterClass($result);
        
        $result->setReporter($reporter); 
        
        foreach ($runnable as $behaviourContextReflection) {
            $contextObject = $behaviourContextReflection->newInstance();
            $collection = new PHPSpec_Runner_Collection($contextObject);
            $runner = PHPSpec_Runner_Base::execute($collection, $result);
        }
        
        $result->setRuntimeEnd(microtime(true));

        $reporter->output($generateSpecdox);
        
        unset($reporter, $result, $runner, $runnable, $collection,
            $contextObject, $behaviourContextReflection);

    }

    protected static function _toObject(array $optionArray) 
    {
        $options = new stdClass;
        foreach ($optionArray as $key=>$value) {
            $options->$key = $value;
        }
        return $options;
    }

}