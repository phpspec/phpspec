--TEST--
Should return TRUE if expected value is equal to (==) actual value
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/Be.php';

$be = new PHPSpec_Matcher_Be(0);
assert('$be->matches(0)');

?>
===DONE===
--EXPECT--
===DONE===