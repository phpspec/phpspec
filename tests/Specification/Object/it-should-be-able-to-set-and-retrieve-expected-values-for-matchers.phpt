--TEST--
Should be able to set and retrieve expected values for matchers
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification_Object::getSpec('Foo');
$spec->setExpectedValue(1);
assert('$spec->getExpectedValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===