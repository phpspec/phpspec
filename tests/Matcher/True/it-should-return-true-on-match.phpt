--TEST--
Should return TRUE if actual value is a boolean TRUE
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_BeTrue(true);
assert('$true->matches(true)');

?>
===DONE===
--EXPECT--
===DONE===