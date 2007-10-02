--TEST--
Should have an Expectation object available
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

$spec = new PHPSpec_Specification;
assert('is_a($spec->getExpectation(), "PHPSpec_Expectation")');

?>
===DONE===
--EXPECT--
===DONE===