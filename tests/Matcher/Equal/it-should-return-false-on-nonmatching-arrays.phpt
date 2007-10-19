--TEST--
Should return False when not matching an array
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(array(1,2,3));
assert('!$equal->matches(array(1,2,4))');

?>
===DONE===
--EXPECT--
===DONE===