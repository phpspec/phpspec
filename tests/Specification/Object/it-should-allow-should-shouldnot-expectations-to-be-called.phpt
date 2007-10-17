--TEST--
Should allow should and shouldnot expectations to be called
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification_Object::getSpec('Foo');

$spec->should();
assert('$spec->getExpectation()->getExpectedMatcherResult() === true');

$spec->shouldNot();
assert('$spec->getExpectation()->getExpectedMatcherResult() === false');

?>
===DONE===
--EXPECT--
===DONE===