--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$greater = new PHPSpec_Matcher_BeGreaterThan(1);
$greater->matches(0);
assert('
$greater->getNegativeFailureMessage() 
    == "expected 0 not to be greater than 1 (using beGreaterThan())"
');

?>
===DONE===
--EXPECT--
===DONE===