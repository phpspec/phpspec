--TEST--
Accepts a Context object as a constructor parameter
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$class = new ReflectionClass('PHPSpec_Runner_Example');
$params = $class->getMethod('__construct')->getParameters();
$type = $params[0]->getClass()->getName();

assert('$type == "PHPSpec_Context"');

?>
===DONE===
--EXPECT--
===DONE===