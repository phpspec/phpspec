--TEST--
Should execute each example in sequence after an execute() call accepting a Result object
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
    public function itShouldHaveZeroElements(){}
    public function itShouldHaveOneElementAfterPush(){}
}

class Mock extends PHPSpec_Runner_Example
{
    public function execute() {echo '.';}
}

class Mock2 extends PHPSpec_Runner_Result
{
    public function __construct(){}
}


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray, 'Mock');
$collection->execute(new Mock2);

?>
--EXPECT--
...