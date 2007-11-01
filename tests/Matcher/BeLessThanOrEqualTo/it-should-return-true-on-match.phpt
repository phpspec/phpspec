--TEST--
Should return TRUE if actual value less than or equal to (<=) expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$less = new PHPSpec_Matcher_BeLessThanOrEqualTo(1);
assert('$less->matches(0)');

?>
===DONE===
--EXPECT--
===DONE===