--TEST--
When a new Context is created it should be self-described
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeEmptyArray extends PHPSpec_Context {
}

$context = new describeEmptyArray;

assert('$context->getDescription() == "describe empty array"');

?>
===DONE===
--EXPECT--
===DONE===