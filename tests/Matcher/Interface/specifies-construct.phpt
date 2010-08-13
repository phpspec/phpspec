--TEST--
All PHPSpec_Matcher_Interface objects should implement a __construct() with
a single, required parameter of $expected
--FILE--
<?php

require_once dirname(__FILE__) . '/../../_setup.inc';

$reflection = new ReflectionClass('PHPSpec_Matcher_Interface');
assert('$reflection->hasMethod("__construct")');

$constructor = new ReflectionMethod('PHPSpec_Matcher_Interface', '__construct');
assert('$constructor->getNumberOfParameters() == 1');
$parameters = $constructor->getParameters();
$param = array_shift($parameters);
assert('$param->isOptional() == false');

?>
===DONE===
--EXPECT--
===DONE===