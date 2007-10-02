--TEST--
Should be able to be instantiated from a static method getSpec with variable params
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');
assert('$spec instanceof PHPSpec_Specification')

?>
===DONE===
--EXPECT--
===DONE===