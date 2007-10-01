--TEST--
Should return TRUE if actual Object is an instance of expected class type
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/AnInstanceOf.php';

class Bar {}
$bar = new Bar;

$be = new PHPSpec_Matcher_AnInstanceOf('Bar');
assert('$be->matches($bar)');

?>
===DONE===
--EXPECT--
===DONE===