--TEST--
Should accept an existing source object when instantiated
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Object/Interrogator.php';

class Foo {
}

$foo = new Foo;

$proxy = new PHPSpec_Object_Interrogator($foo);
assert('is_a($proxy->getSourceObject(), "Foo")');



?>
===DONE===
--EXPECT--
===DONE===