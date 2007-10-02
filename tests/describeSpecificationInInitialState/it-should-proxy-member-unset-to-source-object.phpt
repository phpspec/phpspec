--TEST--
Should proxy member unset check to source object
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {
    public $arg1 = 1;
}

$spec = PHPSpec_Specification::getSpec('Foo');
unset($spec->arg1);

assert('!isset($spec->arg1)');

?>
===DONE===
--EXPECT--
===DONE===