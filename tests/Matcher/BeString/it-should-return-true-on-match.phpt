--TEST--
Should return TRUE if actual value is a string
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeString(null);
assert('$null->matches("string")');

?>
===DONE===
--EXPECT--
===DONE===