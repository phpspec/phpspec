--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/AnInstanceOf.php';

class Bar {}
class Foo {}
$foo = new Foo;

$be = new PHPSpec_Matcher_AnInstanceOf('Bar');
$be->matches($foo);

echo $be->getFailureMessage();

?>
--EXPECT--
expected Bar, got Foo (using anInstanceOf())