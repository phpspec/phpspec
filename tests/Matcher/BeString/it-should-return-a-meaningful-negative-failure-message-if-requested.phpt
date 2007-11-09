--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeString(null);
$null->matches(1);
echo $null->getNegativeFailureMessage();

?>
--EXPECT--
expected 1 not to be string (using beString())