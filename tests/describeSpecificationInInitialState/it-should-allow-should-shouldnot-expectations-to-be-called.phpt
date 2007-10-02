--TEST--
Should allow should and shouldnot expectations to be called
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');

$spec->should();
assert('$spec->getExpectation()->getExpectedMatcherResult() === true');

$spec->shouldNot();
assert('$spec->getExpectation()->getExpectedMatcherResult() === false');

?>
===DONE===
--EXPECT--
===DONE===