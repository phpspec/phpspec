--TEST--
Should capture any Exceptions thrown when executing an Example in the Result object
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){
        throw new Exception();
    }
}


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray);
$result = new PHPSpec_Runner_Result;
$collection->execute($result);

$exceptionArray = $result->getExceptions();
$exceptionExpected = $exceptionArray[0];

assert('$exceptionExpected instanceof PHPSpec_Runner_Example_Exception');

?>
===DONE===
--EXPECT--
===DONE===