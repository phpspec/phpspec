--TEST--
Should return instance of self when setting a negative expectation
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Expectation.php';

$expect = new PHPSpec_Expectation;
$returned = $expect->shouldNot();

assert('$returned === $expect');

?>
===DONE===
--EXPECT--
===DONE===