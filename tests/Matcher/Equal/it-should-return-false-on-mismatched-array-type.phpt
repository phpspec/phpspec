--TEST--
Should return False on mismatched array type
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(1);
assert('!$equal->matches(array())');

?>
===DONE===
--EXPECT--
===DONE===