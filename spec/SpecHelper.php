<?php

require_once 'Mockery/Loader.php';
require_once 'Hamcrest/Hamcrest.php';
$loader = new \Mockery\Loader;
$loader->register();

\PHPSpec\PHPSpec::setTestingPHPSpec(true);

defined('THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR') or
    define('THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR', 'ignored');
