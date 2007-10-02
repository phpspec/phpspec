--TEST--
Should return instance of self when setting a negative expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$expect = new PHPSpec_Expectation;
$returned = $expect->shouldNot();

assert('$returned === $expect');

?>
===DONE===
--EXPECT--
===DONE===