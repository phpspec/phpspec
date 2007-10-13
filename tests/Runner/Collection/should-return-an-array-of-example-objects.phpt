--TEST--
Should return an array of PHPSpec_Runner_Example objects from getExamples()
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){}
    public function itShouldHaveZeroElements(){}
}


$collection = new PHPSpec_Runner_Collection(new describeEmptyArray);
$examples = $collection->getExamples();
foreach ($examples as $example) {
    assert('$example instanceof PHPSpec_Runner_Example');
}

?>
===DONE===
--EXPECT--
===DONE===