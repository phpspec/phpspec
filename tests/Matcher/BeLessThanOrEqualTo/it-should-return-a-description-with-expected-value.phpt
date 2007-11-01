--TEST--
Should return a description with expected value
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$less = new PHPSpec_Matcher_BeLessThanOrEqualTo(1);
echo $less->getDescription();

?>
--EXPECT--
be less than or equal to 1