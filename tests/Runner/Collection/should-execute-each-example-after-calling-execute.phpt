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

class Mock3 extends PHPSpec_Runner_Reporter_Text {
    public function __construct() {}
    public function outputStatus() {}
}


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray, 'Mock');
$result = new Mock2;
$result->setReporter(new Mock3);
$collection->execute($result);

?>
--EXPECT--
...