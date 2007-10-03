--TEST--
Should return FALSE if expected value not equal to (!==) actual value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(0);
assert('!$equal->matches(1)');

?>
===DONE===
--EXPECT--
===DONE===