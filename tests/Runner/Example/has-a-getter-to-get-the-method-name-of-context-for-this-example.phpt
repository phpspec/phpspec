--TEST--
The getter getMethodName() returns the method of the Context class we wll execute for this example
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
}

$ex = new PHPSpec_Runner_Example(new describeEmptyArray, 'itShouldBeEmpty');

assert('$ex->getMethodName() == "itShouldBeEmpty"');

?>
===DONE===
--EXPECT--
===DONE===