--TEST--
Should return TRUE if expected value is equal to (==) actual value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$be = new PHPSpec_Matcher_Be(0);
assert('$be->matches(0)');

?>
===DONE===
--EXPECT--
===DONE===