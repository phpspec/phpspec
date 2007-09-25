--TEST--
Should return FALSE if expected value not equal to (!==) actual value
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/Equal.php';

$equal = new PHPSpec_Matcher_Equal(0);
assert('!$equal->matches(1)');

?>
===DONE===
--EXPECT--
===DONE===