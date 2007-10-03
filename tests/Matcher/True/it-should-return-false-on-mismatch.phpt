--TEST--
Should return FALSE if actual value is not a boolean TRUE
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_True(true);
assert('!$true->matches(false)');

?>
===DONE===
--EXPECT--
===DONE===