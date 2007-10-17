--TEST--
Should return a description of the expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_BeTrue(true);
echo $true->getDescription();

?>
--EXPECT--
be TRUE