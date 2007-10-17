--TEST--
Should allow an empty be() call to pass back Spec object since it's just a sugar call for grammer
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class RunnerSwallow {
    public function notify() {}
}

$spec = PHPSpec_Specification_Scalar::getSpec(100);
$spec->setRunner(new RunnerSwallow); // isolate default phpt runner

$spec->should()->be()->greaterThan(0);
assert($spec->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===