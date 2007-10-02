--TEST--
Should be able to be instantiated from a static method getSpec with variable params
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');
assert('is_a($spec, "PHPSpec_Specification")')

?>
===DONE===
--EXPECT--
===DONE===