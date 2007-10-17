--TEST--
Should allow an empty be() call to pass back Spec object since it's just a sugar call for grammer
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {
    public $member = 1;
}

class RunnerSwallow {
    public function notify() {}
}

$foo = new Foo;
$spec = PHPSpec_Specification_Object::getSpec($foo);
$spec->setRunner(new RunnerSwallow); // isolate default phpt runner

$spec->member->should()->be()->beGreaterThan(0);
assert($spec->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===