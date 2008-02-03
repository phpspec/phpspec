<?php

require_once 'Zend/Controller/Front.php';

class Bootstrap
{

    public static function prepare()
    {
        error_reporting(E_ALL|E_STRICT);
        date_default_timezone_set('Europe/London');

        // setup front controller
        $frontController = Zend_Controller_Front::getInstance();
        $frontController->throwExceptions(true);
        $frontController->setControllerDirectory('./application/controllers');
    }

    public static function dispatch($frontController) 
    {
        Zend_Controller_Front::getInstance()->dispatch();
    }

}