--TEST--
Should run a matcher allowing for optional arguments
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$emptyArray = array();

class RunnerSwallow {
    public function notify() {}
}

$spec = PHPSpec_Specification_Scalar::getSpec( 0.123 );
$spec->setRunner(new RunnerSwallow); // isolate default phpt runner

$spec->should()->equal(0.123, 0.001);
assert($spec->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===