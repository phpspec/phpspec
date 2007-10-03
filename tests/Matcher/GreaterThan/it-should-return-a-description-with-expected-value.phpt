--TEST--
Should return a description with expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$greater = new PHPSpec_Matcher_GreaterThan(1);
echo $greater->getDescription();

?>
--EXPECT--
greater than 1