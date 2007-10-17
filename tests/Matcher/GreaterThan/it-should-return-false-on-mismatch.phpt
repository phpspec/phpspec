--TEST--
Should return FALSE if actual value not greater than (>) expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$greater = new PHPSpec_Matcher_BeGreaterThan(1);
assert('!$greater->matches(0)');

?>
===DONE===
--EXPECT--
===DONE===