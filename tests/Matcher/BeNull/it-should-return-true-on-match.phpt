--TEST--
Should return TRUE if actual value is NULL
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeNull(null);
assert('$null->matches(null)');

?>
===DONE===
--EXPECT--
===DONE===