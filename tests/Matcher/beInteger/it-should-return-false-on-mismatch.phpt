--TEST--
Should return FALSE if actual value is not an integer
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeInteger(null);
assert('!$null->matches("x")');
assert('!$null->matches(array())');
assert('!$null->matches("1")');

?>
===DONE===
--EXPECT--
===DONE===