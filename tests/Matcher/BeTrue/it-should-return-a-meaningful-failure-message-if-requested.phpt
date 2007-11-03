--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock {
    public function hasAClue() {
        return false;
    }
}

$predicate = new PHPSpec_Matcher_Predicate(true);
$predicate->setMethodName('hasAClue');
$predicate->setObject(new Mock);
$predicate->setPredicateCall('haveAClue');

$predicate->matches(true);
assert('$predicate->getFailureMessage() == "expected TRUE, got FALSE or non-boolean (using haveAClue())"');

?>
===DONE===
--EXPECT--
===DONE===