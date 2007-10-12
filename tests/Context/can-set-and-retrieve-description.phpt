--TEST--
Can set and retrieve a Context description
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
}

$context = new describeBoo;
$context->setDescription('abc');
assert('$context->getDescription() == "abc"');

?>
===DONE===
--EXPECT--
===DONE===