--TEST--
Should run a matcher and store result if a known matcher method is called
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {
    public $member = 1;
}

$foo = PHPSpec_Specification::getSpec('Foo');

$foo->member->should()->equal(1);
assert($foo->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===