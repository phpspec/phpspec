--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$be = new PHPSpec_Matcher_Be(1);
$be->matches(0);

echo $be->getNegativeFailureMessage();

?>
--EXPECT--
expected 0 not to be 1 (using be())