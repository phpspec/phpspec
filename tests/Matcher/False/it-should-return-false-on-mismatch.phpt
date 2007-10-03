--TEST--
Should return FALSE if actual value is not a boolean FALSE
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_False(false);
assert('!$false->matches(true)');

?>
===DONE===
--EXPECT--
===DONE===