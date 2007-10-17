--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_BeTrue(true);
$true->matches(false);
assert('$true->getFailureMessage() == "expected TRUE, got FALSE or non-boolean (using beTrue())"');

?>
===DONE===
--EXPECT--
===DONE===