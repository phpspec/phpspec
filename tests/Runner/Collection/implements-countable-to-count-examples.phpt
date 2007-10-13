--TEST--
Should implement SPL Countable to count Examples
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
    public function itShouldHaveZeroElements(){}
    public function itShouldHaveOneElementAfterPush(){}
}


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray);
assert('count($collection) == 3');

?>
===DONE===
--EXPECT--
===DONE===