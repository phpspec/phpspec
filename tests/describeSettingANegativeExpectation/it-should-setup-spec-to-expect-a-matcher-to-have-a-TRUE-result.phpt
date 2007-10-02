--TEST--
Should setup spec to expect a matcher having a FALSE result
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Expectation.php';

$expect = new PHPSpec_Expectation;
$expect->shouldNot();

assert('$expect->getExpectedMatcherResult() === false');

?>
===DONE===
--EXPECT--
===DONE===