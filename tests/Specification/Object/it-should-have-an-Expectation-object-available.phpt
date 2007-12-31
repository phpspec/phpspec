--TEST--
Should have an Expectation object available
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$interrogator = new PHPSpec_Object_Interrogator('stdClass');
$spec = new PHPSpec_Specification_Object($interrogator);
$exp = $spec->getExpectation();
//var_dump($exp); exit;
assert('is_a($exp, "PHPSpec_Expectation")');
//assert("$exp instanceof PHPSpec_Expectation");

?>
===DONE===
--EXPECT--
===DONE===