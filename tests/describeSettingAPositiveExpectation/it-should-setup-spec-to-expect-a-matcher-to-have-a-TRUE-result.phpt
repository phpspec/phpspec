--TEST--
Should return TRUE if actual Object is an instance of expected class type
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Expectation.php';

$expect = new PHPSpec_Expectation;
$expect->should();

assert('$expect->getExpectedMatcherResult() === true');

?>
===DONE===
--EXPECT--
===DONE===