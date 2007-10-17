--TEST--
Should return TRUE if actual Object is an instance of expected class type
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Bar {}
$bar = new Bar;

$be = new PHPSpec_Matcher_BeAnInstanceOf('Bar');
assert('$be->matches($bar)');

?>
===DONE===
--EXPECT--
===DONE===