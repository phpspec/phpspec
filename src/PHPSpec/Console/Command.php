<?php

require_once 'PHPSpec/Framework.php';

class PHPSpec_Console_Command
{

    protected static $_getopt = null;

    public static function main()
    {
        self::$_getopt = new PHPSpec_Console_Getopt;
        $context = self::$_getopt->specFile;

        // run the spec context manually for now
        // result object already echoes everything so no new Reporter/Result needed

        $contextFile = $context . '.php';
        require_once $contextFile;
        $contextObject = new $context;
        $collection = new PHPSpec_Runner_Collection($contextObject);
        $runner = PHPSpec_Runner_Base::execute($collection);

    }

}

PHPSpec_Console_Command::main();