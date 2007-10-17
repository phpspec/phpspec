--TEST--
Should have an Expectation object available
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$spec = new PHPSpec_Specification_Object;
assert('is_a($spec->getExpectation(), "PHPSpec_Expectation")');

?>
===DONE===
--EXPECT--
===DONE===