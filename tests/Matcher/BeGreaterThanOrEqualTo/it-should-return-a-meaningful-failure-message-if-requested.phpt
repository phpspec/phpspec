--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$greater = new PHPSpec_Matcher_BeGreaterThanOrEqualTo(1);
$greater->matches(0);
assert('$greater->getFailureMessage() == "expected greater than or equal to 1, got 0 (using beGreaterThanOrEqualTo())"');

?>
===DONE===
--EXPECT--
===DONE===