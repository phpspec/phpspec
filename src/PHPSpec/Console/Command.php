<?php

require_once 'PHPSpec/Framework.php';

class PHPSpec_Console_Command
{

    protected static $_getopt = null;

    protected static $_runnable = array();

    public static function main()
    {
        self::$_getopt = new PHPSpec_Console_Getopt;

        // check for straight class to execute
        if (isset(self::$_getopt->specFile)) {
            $loader = new PHPSpec_Runner_Loader_Classname;
            self::$_runnable[] = $loader->load(self::$_getopt->specFile);
        }

        if (empty($self::$_runnable)) {
            echo 'No specs to execute!';
            return;
        }

        foreach(self::$_runnable as $behaviourContextReflection) {
            $contextObject = $behaviourContextReflection->newInstance();
            $collection = new PHPSpec_Runner_Collection($contextObject);
            $runner = PHPSpec_Runner_Base::execute($collection);
        }
        
    }

}

PHPSpec_Console_Command::main();