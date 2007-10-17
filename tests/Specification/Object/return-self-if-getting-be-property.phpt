--TEST--
Instead of calling be(), allow a 'be' property to also behave the same as be() with no arguments.
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification_Object::getSpec('Foo');

$return = $spec->be;

assert('$return instanceof PHPSpec_Specification_Object');

?>
===DONE===
--EXPECT--
===DONE===