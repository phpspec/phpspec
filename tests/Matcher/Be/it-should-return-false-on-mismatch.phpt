--TEST--
Should return FALSE if expected value not equal to (!==) actual value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$be = new PHPSpec_Matcher_Be(0);
assert('!$be->matches(1)');

?>
===DONE===
--EXPECT--
===DONE===