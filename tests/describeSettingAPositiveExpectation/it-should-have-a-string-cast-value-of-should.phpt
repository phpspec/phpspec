--TEST--
Should have a string cast value of "should"
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$expect = new PHPSpec_Expectation;
$expect->should();

echo $expect;

?>
--EXPECT--
should