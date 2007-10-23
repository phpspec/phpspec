<?php

require_once 'PHPSpec/Framework.php';

class PHPSpec_Console_Command
{

    public static function main()
    {
        $runnable = array();
        $options = new PHPSpec_Console_Getopt;

        // check for straight class to execute
        if (isset($options->specFile)) {
            $loader = new PHPSpec_Runner_Loader_Classname;
            $runnable += $loader->load($options->specFile);
        }

        if (isset($options->r)) {
            $loader = new PHPSpec_Runner_Loader_DirectoryRecursive;
            $runnable += $loader->load( getcwd() );
        }

        if (empty($runnable)) {
            echo 'No specs to execute!';
            return;
        }

        $specCount = 0;
        $result = new PHPSpec_Runner_Result;
        foreach ($runnable as $behaviourContextReflection) {
            $contextObject = $behaviourContextReflection->newInstance();
            $collection = new PHPSpec_Runner_Collection($contextObject);
            $runner = PHPSpec_Runner_Base::execute($collection, $result);
            $specCount += count($runner);
        }

        echo $specCount, ' Specs Executed:', PHP_EOL;
        echo $runner->getResult();
        
    }

}

PHPSpec_Console_Command::main();