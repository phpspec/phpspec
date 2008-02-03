--TEST--
Should return TRUE if actual value matches given regex
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$match = new PHPSpec_Matcher_Match("/^php/i");
assert('$match->matches("PHP5 On Steroids")');

?>
===DONE===
--EXPECT--
===DONE===