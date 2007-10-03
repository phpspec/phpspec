--TEST--
Should create a Specification object from the parameter(s)
--FILE--
<?php

require_once dirname(__FILE__) . '/../../../src/PHPSpec/Framework.php';

class Foo {
}

$foo = new Foo;

$result = describe($foo);
assert('$result instanceof PHPSpec_Specification');


?>
===DONE===
--EXPECT--
===DONE===