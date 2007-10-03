--TEST--
Should return TRUE if expected value is equal to (==) actual value
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(0);
assert('$equal->matches(0)');

?>
===DONE===
--EXPECT--
===DONE===