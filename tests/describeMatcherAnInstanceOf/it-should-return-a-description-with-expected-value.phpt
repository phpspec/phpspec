--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Bar {}
class Foo {}
$foo = new Foo;

$be = new PHPSpec_Matcher_AnInstanceOf('Bar');
$be->matches($foo);
echo $be->getDescription();

?>
--EXPECT--
be an instance of Bar