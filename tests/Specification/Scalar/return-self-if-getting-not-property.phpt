--TEST--
Instead of calling shouldNot(), allow a 'shouldNot' property to also behave the same.
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);

$return = $spec->shouldNot;

assert('$spec->getExpectation()->getExpectedMatcherResult() === false');
assert('$return instanceof PHPSpec_Specification_Scalar');

?>
===DONE===
--EXPECT--
===DONE===