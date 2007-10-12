--TEST--
Can retrieve an array of Spec methods from a context
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
    public function itShouldBeTrue() {}
    public function itShouldBeFalse() {}
}

$context = new describeBoo;

$expected = array('itShouldBeTrue', 'itShouldBeFalse');

assert('$context->getSpecMethods() == $expected');

?>
===DONE===
--EXPECT--
===DONE===