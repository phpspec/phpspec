--TEST--
The getter getContextDescription returns a readable description string for the Context
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
}

$ex = new PHPSpec_Runner_Example(new describeEmptyArray, 'itShouldBeEmpty');

assert('$ex->getContextDescription() == "describe empty array"');

?>
===DONE===
--EXPECT--
===DONE===