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

function describe()
{
    $args = func_get_args();
    return call_user_func_array(array('PHPSpec_Specification','getSpec'), $args);
}

function PHPSpec_ErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!($errno & error_reporting())) {
        return;
    }

    $backtrace = debug_backtrace();
    array_shift($backtrace);

    throw new PHPSpec_Runner_ErrorException($errstr, $errno, $errfile, $errline, $backtrace);

    return true;
}