--TEST--
Should allow should and shouldnot expectations to be called
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);

$spec->should();
assert('$spec->getExpectation()->getExpectedMatcherResult() === true');

$spec->shouldNot();
assert('$spec->getExpectation()->getExpectedMatcherResult() === false');

?>
===DONE===
--EXPECT--
===DONE===