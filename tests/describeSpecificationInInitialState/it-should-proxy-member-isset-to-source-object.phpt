--TEST--
Should proxy member isset check to source object
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {
    public $arg1 = null;
}

$spec = PHPSpec_Specification::getSpec('Foo');

assert('!isset($spec->arg1)');

?>
===DONE===
--EXPECT--
===DONE===