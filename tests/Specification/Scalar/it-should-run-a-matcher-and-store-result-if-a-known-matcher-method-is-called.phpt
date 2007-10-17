--TEST--
Should run a matcher and store result if a known matcher method is called
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$emptyArray = array();

class RunnerSwallow {
    public function notify() {}
}

$spec = PHPSpec_Specification_Scalar::getSpec( $emptyArray );
$spec->setRunner(new RunnerSwallow); // isolate default phpt runner

$spec->should()->beEmpty();
assert($spec->getMatcherResult() === true);

?>
===DONE===
--EXPECT--
===DONE===