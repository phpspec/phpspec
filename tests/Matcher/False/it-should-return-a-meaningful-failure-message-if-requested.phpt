--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_False(false);
$false->matches(true);
assert('$false->getFailureMessage() == "expected FALSE, got TRUE or non-boolean (using false())"');

?>
===DONE===
--EXPECT--
===DONE===