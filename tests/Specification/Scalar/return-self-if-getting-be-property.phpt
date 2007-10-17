--TEST--
Instead of calling be(), allow a 'be' property to also behave the same as be() with no arguments.
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);

$return = $spec->be;

assert('$return instanceof PHPSpec_Specification_Scalar');

?>
===DONE===
--EXPECT--
===DONE===