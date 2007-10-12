--TEST--
getCurrentSpecification method returns an instance of PHPSpec_Specification
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
}

$context = new describeBoo;

class Foo{}

$context->spec(new Foo);

assert('$context->getCurrentSpecification() instanceof PHPSpec_Specification');

?>
===DONE===
--EXPECT--
===DONE===