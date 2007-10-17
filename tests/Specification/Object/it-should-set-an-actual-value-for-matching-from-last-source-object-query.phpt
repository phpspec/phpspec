--TEST--
Should set an actual value for matching from last source object query
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {
    public function get() {
        return 1;
    }
}

$spec = PHPSpec_Specification_Object::getSpec('Foo');

$spec->get();
assert('$spec->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===