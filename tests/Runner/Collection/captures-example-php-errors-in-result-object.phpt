--TEST--
Should capture any PHP Errors when executing an Example in the Result object
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){
        trigger_error('I have failed!', E_USER_ERROR);
    }
}
class Mock3 extends PHPSpec_Runner_Reporter_Text {
    public function __construct() {}
    public function outputStatus($symbol) {}
}


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray);
$result = new PHPSpec_Runner_Result;
$result->setReporter(new Mock3);
$collection->execute($result);

$exceptionArray = $result->getTypes('error');
$exceptionExpected = $exceptionArray[0];

assert('$exceptionExpected instanceof PHPSpec_Runner_Example_Error');

?>
===DONE===
--EXPECT--
===DONE===