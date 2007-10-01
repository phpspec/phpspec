--TEST--
Should return FALSE if expected value not equal to (!==) actual value
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/Be.php';

$be = new PHPSpec_Matcher_Be(0);
assert('!$be->matches(1)');

?>
===DONE===
--EXPECT--
===DONE===