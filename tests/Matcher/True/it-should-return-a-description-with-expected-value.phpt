--TEST--
Should return a description of the expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_True(true);
echo $true->getDescription();

?>
--EXPECT--
TRUE