--TEST--
Should accept an existing source object when instantiated
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Object/Proxy.php';

class Foo {
}

$foo = new Foo;

$proxy = new PHPSpec_Object_Proxy($foo);
assert('is_a($proxy->getSourceObject(), "Foo")');



?>
===DONE===
--EXPECT--
===DONE===