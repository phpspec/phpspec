--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_BeInteger(null);
$null->matches('x');
echo $null->getNegativeFailureMessage();

?>
--EXPECT--
expected x not to be integer (using beInteger())