--TEST--
Can get the number of specification a context contains
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
    public function itShouldBeTrue() {}
    public function itShouldBeFalse() {}
}

$context = new describeBoo;

assert('$context->getSpecificationCount() == 2');

?>
===DONE===
--EXPECT--
===DONE===