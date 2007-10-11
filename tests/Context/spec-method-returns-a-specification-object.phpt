--TEST--
The spec() method should return a Specification object
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
}

$context = new describeBoo;

class Foo{}

assert('$context->spec(new Foo) instanceof PHPSpec_Specification');

?>
===DONE===
--EXPECT--
===DONE===