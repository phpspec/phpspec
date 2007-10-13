--TEST--
Should have the description of the Context available for retrieval
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
}

$collection = new PHPSpec_Runner_Collection(new describeEmptyArray);


assert('$collection->getDescription() == "describe empty array"');

?>
===DONE===
--EXPECT--
===DONE===