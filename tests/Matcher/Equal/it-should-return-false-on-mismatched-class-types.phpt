--TEST--
Should return False on mismatched class types
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$equal = new PHPSpec_Matcher_Equal(new stdClass);

class Foo {}

assert('!$equal->matches(new Foo)');

?>
===DONE===
--EXPECT--
===DONE===