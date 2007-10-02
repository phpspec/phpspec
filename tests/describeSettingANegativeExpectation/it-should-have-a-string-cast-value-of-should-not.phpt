--TEST--
Should have a string cast value of "should not"
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Expectation.php';

$expect = new PHPSpec_Expectation;
$expect->shouldNot();

echo $expect;

?>
--EXPECT--
should not