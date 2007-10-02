--TEST--
Should have a string cast value of "should"
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Expectation.php';

$expect = new PHPSpec_Expectation;
$expect->should();

echo $expect;

?>
--EXPECT--
should