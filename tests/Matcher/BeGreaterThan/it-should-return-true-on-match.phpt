--TEST--
Should return TRUE if actual value greater than (>) expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$greater = new PHPSpec_Matcher_BeGreaterThan(1);
assert('$greater->matches(2)');

?>
===DONE===
--EXPECT--
===DONE===