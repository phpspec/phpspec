--TEST--
Should return instance of self when setting positive expectation
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Expectation.php';

$expect = new PHPSpec_Expectation;
$returned = $expect->should();

assert('$returned === $expect');

?>
===DONE===
--EXPECT--
===DONE===