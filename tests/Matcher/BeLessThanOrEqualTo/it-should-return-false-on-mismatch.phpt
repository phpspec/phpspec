--TEST--
Should return FALSE if actual value not less than or equal to (<=) expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$less = new PHPSpec_Matcher_BeLessThanOrEqualTo(0);
assert('!$less->matches(1)');

?>
===DONE===
--EXPECT--
===DONE===