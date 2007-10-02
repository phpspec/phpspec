<?php

class PHPSpec_Framework
{
    public function __construct()
    {
        
    }
    
    public static function autoload($class)
    {
        // @todo consider speed implications
        if (substr($class, 0, 8) != 'PHPSpec_') {
            return false;
        }
        $path = dirname(dirname(__FILE__));
        include_once $path . '/' . str_replace('_', '/', $class) . '.php';
    }
}

spl_autoload_register(array(
    'PHPSpec_Framework',
    'autoload'
));