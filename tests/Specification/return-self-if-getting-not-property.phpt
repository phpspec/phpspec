--TEST--
Instead of calling shouldNot(), allow a 'shouldNot' property to also behave the same.
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');

$return = $spec->shouldNot;

assert('$spec->getExpectation()->getExpectedMatcherResult() === false');
assert('$return instanceof PHPSpec_Specification');

?>
===DONE===
--EXPECT--
===DONE===