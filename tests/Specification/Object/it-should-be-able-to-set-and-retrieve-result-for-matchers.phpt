--TEST--
Should be able to set and retrieve result for matchers
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {}

$spec = PHPSpec_Specification_Object::getSpec('Foo');
$spec->setMatcherResult(true);
assert('$spec->getMatcherResult() === true');

?>
===DONE===
--EXPECT--
===DONE===