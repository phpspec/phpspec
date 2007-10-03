--TEST--
Should have a string cast value of "should not"
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$expect = new PHPSpec_Expectation;
$expect->shouldNot();

echo $expect;

?>
--EXPECT--
should not