<?php

set_include_path('.' . PATH_SEPARATOR . dirname(dirname(__FILE__)) .
                       '/src/' . PATH_SEPARATOR .
                       get_include_path());

require_once 'PHPSpec/Framework.php';
 
if (!defined('PHPSPEC_BIN')) {
    define('PHPSPEC_BIN', '/usr/bin/phpspec');	
} 
if (!defined('TESTS_ROOT_DIR')) {
    define('TESTS_ROOT_DIR', realpath(dirname(__FILE__)));	
}

define("THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR", 'ignored');