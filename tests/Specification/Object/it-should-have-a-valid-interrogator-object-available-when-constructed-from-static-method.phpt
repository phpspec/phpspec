--TEST--
Should have a valid Interrogator object available when constructed from static method
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification_Object::getSpec('Foo');
assert('is_a($spec->getInterrogator()->getSourceObject(), "Foo")')

?>
===DONE===
--EXPECT--
===DONE===