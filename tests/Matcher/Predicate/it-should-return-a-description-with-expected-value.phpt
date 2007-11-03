--TEST--
Should return a description of the expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock {
    public function hasAClue() {
        return true;
    }
}

$predicate = new PHPSpec_Matcher_Predicate(true);
$predicate->setMethodName('hasAClue');
$predicate->setObject(new Mock);
$predicate->setPredicateCall('haveAClue');

assert('$predicate->getDescription() == "have a clue"')

?>
===DONE===
--EXPECT--
===DONE===