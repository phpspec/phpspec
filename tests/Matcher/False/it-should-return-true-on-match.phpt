--TEST--
Should return TRUE if actual value is a boolean FALSE
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_False(false);
assert('$false->matches(false)');

?>
===DONE===
--EXPECT--
===DONE===