--TEST--
Should run a matcher for has* predicate if called
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Foo {
    public function hasSomething() {
        return true;
    }
}

class RunnerSwallow {
    public function notify() {}
}

$foo = new Foo;
$spec = PHPSpec_Specification_Object::getSpec($foo);
$spec->setRunner(new RunnerSwallow);

$spec->should()->haveSomething();
assert($spec->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===