--TEST--
Should throw an exception if the contructor Example subclass string is invalid
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
}

class Mock // does not extend PHPSpec_Runner_Example
{
}

try {
    $collection = new PHPSpec_Runner_Collection(new describeEmptyArray, 'Mock');
    assert(false);
} catch (PHPSpec_Exception $e) {
    assert(true);
}
?>
===DONE===
--EXPECT--
===DONE===