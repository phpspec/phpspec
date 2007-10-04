--TEST--
Instead of calling should(), allow a should property to also behave the same.
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');

$return = $spec->should;

assert('$spec->getExpectation()->getExpectedMatcherResult() === true');
assert('$return instanceof PHPSpec_Specification');

?>
===DONE===
--EXPECT--
===DONE===