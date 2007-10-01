--TEST--
Should return FALSE if actual Object is not an instance of expected class type
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/AnInstanceOf.php';

class Bar {}
class Foo {}
$foo = new Foo;

$be = new PHPSpec_Matcher_AnInstanceOf('Bar');
assert('!$be->matches($foo)');

?>
===DONE===
--EXPECT--
===DONE===