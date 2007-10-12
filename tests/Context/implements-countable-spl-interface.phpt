--TEST--
Can get the number of specifications a context contains using countable interface
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
    public function itShouldBeTrue() {}
    public function itShouldBeFalse() {}
}

$context = new describeBoo;

assert('count($context) == 2');

?>
===DONE===
--EXPECT--
===DONE===