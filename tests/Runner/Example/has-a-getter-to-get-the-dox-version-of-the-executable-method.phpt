--TEST--
The getter getSpecificationText() returns the Dox version of the method name being executed
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
}

$ex = new PHPSpec_Runner_Example(new describeEmptyArray, 'itShouldBeEmpty');

assert('$ex->getSpecificationText() == "should be empty"');

?>
===DONE===
--EXPECT--
===DONE===