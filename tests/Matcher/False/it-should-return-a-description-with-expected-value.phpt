--TEST--
Should return a description of the expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_BeFalse(false);
$false->matches(false);
echo $false->getDescription();

?>
--EXPECT--
be FALSE