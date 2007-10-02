--TEST--
Should be able to set and retrieve actual values for matchers
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');
$spec->setActualValue(1);
assert('$spec->getActualValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===