--TEST--
Should return TRUE if actual value is an integer
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeInteger(null);
assert('$null->matches(123)');

?>
===DONE===
--EXPECT--
===DONE===