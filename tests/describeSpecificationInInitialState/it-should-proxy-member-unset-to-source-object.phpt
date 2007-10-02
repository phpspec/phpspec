--TEST--
Should proxy member unset check to source object
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

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