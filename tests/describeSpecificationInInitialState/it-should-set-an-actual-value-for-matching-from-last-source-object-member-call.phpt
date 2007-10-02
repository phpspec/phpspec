--TEST--
Should set an actual value for matching from last source object member call
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {
    public $member = 1;
}

$spec = PHPSpec_Specification::getSpec('Foo');

$spec->member;
assert('$spec->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===