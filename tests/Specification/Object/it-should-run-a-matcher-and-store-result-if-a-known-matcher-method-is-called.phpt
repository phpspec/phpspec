--TEST--
Should run a matcher and store result if a known matcher method is called
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

$spec->member->should()->equal(1);
assert($spec->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===