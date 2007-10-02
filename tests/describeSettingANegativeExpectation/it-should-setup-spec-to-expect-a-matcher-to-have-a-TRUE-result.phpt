--TEST--
Should setup spec to expect a matcher having a FALSE result
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$expect = new PHPSpec_Expectation;
$expect->shouldNot();

assert('$expect->getExpectedMatcherResult() === false');

?>
===DONE===
--EXPECT--
===DONE===