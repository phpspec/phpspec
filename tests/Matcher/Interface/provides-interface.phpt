--TEST--
PHPSpec_Matcher_Interface provides an interface for all Matchers to implement
--FILE--
<?php

require_once dirname(__FILE__) . '/../../_setup.inc';

$reflection = new ReflectionClass('PHPSpec_Matcher_Interface');
assert('$reflection->isInterface()');

?>
===DONE===
--EXPECT--
===DONE===