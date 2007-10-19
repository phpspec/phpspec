--TEST--
Should return True when matching floats
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(0.123);
assert('$equal->matches(0.123, 0.0001)');

?>
===DONE===
--EXPECT--
===DONE===