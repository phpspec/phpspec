--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeNull(null);
$null->matches(1);
echo $null->getFailureMessage();

?>
--EXPECT--
expected to be NULL, got 1 (using beNull())