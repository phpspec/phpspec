--TEST--
Should return a description with expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$greater = new PHPSpec_Matcher_BeGreaterThan(1);
echo $greater->getDescription();

?>
--EXPECT--
be greater than 1