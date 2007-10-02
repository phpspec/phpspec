--TEST--
Should set an actual value for matching from last source object query
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {
    public function get() {
        return 1;
    }
}

$spec = PHPSpec_Specification::getSpec('Foo');

$spec->get();
assert('$spec->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===