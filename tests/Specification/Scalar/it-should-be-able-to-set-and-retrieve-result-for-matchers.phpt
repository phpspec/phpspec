--TEST--
Should be able to set and retrieve result for matchers
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = PHPSpec_Specification_Scalar::getSpec(100);
$spec->setMatcherResult(true);
assert('$spec->getMatcherResult() === true');

?>
===DONE===
--EXPECT--
===DONE===