--TEST--
Should have a scalar value available when constructed from static method
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);
assert('$spec->getScalar() == 100')

?>
===DONE===
--EXPECT--
===DONE===