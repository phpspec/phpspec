--TEST--
Should be able to set and retrieve actual values for matchers
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);
$spec->setActualValue(1);
assert('$spec->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===