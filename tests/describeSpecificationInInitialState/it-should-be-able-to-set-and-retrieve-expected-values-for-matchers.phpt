--TEST--
Should be able to set and retrieve expected values for matchers
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');
$spec->setExpectedValue(1);
assert('$spec->getExpectedValue() == 1');

?>
===DONE===
--EXPECT--
===DONE===