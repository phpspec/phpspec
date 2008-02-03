--TEST--
Should return FALSE if actual value does not match given regex
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$match = new PHPSpec_Matcher_Match("/php/");
assert('!$match->matches("ruby")');

?>
===DONE===
--EXPECT--
===DONE===