--TEST--
Should return TRUE if actual value less than (<) expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$less = new PHPSpec_Matcher_BeLessThan(1);
assert('$less->matches(0)');

?>
===DONE===
--EXPECT--
===DONE===