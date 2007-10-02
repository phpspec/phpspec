--TEST--
Should return instance of self when setting positive expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

$expect = new PHPSpec_Expectation;
$returned = $expect->should();

assert('$returned === $expect');

?>
===DONE===
--EXPECT--
===DONE===