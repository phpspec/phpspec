--TEST--
Should be able to set and retrieve expected values for matchers
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);
$spec->setExpectedValue(1);
assert('$spec->getExpectedValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===