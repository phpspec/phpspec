--TEST--
Should return FALSE if actual value is not a boolean FALSE
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeString(null);
assert('!$null->matches(1)');

?>
===DONE===
--EXPECT--
===DONE===