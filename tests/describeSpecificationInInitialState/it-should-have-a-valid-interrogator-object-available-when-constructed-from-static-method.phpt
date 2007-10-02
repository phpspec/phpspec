--TEST--
Should have a valid Interrogator object available when constructed from static method
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');
assert('is_a($spec->getInterrogator()->getSourceObject(), "Foo")')

?>
===DONE===
--EXPECT--
===DONE===