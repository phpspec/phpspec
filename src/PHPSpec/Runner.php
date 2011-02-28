<?php

class PHPSpec_Runner
{

    public function run($options)
    {
        $runnable = array();
        $generateSpecdox = false;
                                    
        // check for straight class to execute
        if (isset($options->specFile)) {
	        $pathToFile = getcwd();
	        if (false !== strpos($options->specFile, '/')) {
		        $pathToFile = str_replace("/" . basename($options->specFile), '', $options->specFile);
		        $options->specFile = basename($options->specFile);
	        }
	        if (is_dir(realpath($pathToFile . "/$options->specFile"))) {
		        $loader = new PHPSpec_Runner_Loader_DirectoryRecursive;
            	$runnable += $loader->load(realpath($pathToFile . "/$options->specFile"));
	        } else {
		        $loader = new PHPSpec_Runner_Loader_Classname;
				$runnable += $loader->load($options->specFile, $pathToFile);
	        }
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
            echo 'No specs to execute!' . PHP_EOL;
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
        if ($options->c || $options->color || $options->colour) {
			$reporter->showColors(true);
		}
        
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
}
