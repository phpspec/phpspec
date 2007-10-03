--TEST--
Should return TRUE if actual Object is an instance of expected class type
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$expect = new PHPSpec_Expectation;
$expect->should();

assert('$expect->getExpectedMatcherResult() === true');

?>
===DONE===
--EXPECT--
===DONE===