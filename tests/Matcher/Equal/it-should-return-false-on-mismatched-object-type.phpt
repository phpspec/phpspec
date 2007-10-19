--TEST--
Should return False on mismatched object type
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(new stdClass);
assert('!$equal->matches(array())');

?>
===DONE===
--EXPECT--
===DONE===