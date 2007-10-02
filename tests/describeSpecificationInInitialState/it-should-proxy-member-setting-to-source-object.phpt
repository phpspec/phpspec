--TEST--
Should proxy member setting to source object
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {
    public $arg1 = null;
}

$spec = PHPSpec_Specification::getSpec('Foo');

$spec->arg1 = 1;
assert('$spec->arg1->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===