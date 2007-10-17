--TEST--
Should set an actual value for matching from last source object member call
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {
    public $member = 1;
}

$spec = PHPSpec_Specification_Object::getSpec('Foo');

$spec->member;
assert('$spec->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===