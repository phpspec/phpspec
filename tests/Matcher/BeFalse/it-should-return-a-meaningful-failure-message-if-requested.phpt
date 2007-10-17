--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_BeFalse(false);
$false->matches(true);
assert('$false->getFailureMessage() == "expected FALSE, got TRUE or non-boolean (using beFalse())"');

?>
===DONE===
--EXPECT--
===DONE===