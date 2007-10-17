--TEST--
Should be able to be instantiated from a static method getSpec with variable params
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';


$spec = PHPSpec_Specification_Scalar::getSpec(100);
assert('$spec instanceof PHPSpec_Specification_Scalar')

?>
===DONE===
--EXPECT--
===DONE===