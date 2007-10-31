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


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray);
$result = new PHPSpec_Runner_Result;
$collection->execute($result);

$exceptionArray = $result->getExceptions();
$exceptionExpected = $exceptionArray[0][1];

assert('$exceptionExpected instanceof PHPSpec_Runner_ErrorException');

?>
===DONE===
--EXPECT--
===DONE===