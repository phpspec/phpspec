--TEST--
Should return TRUE if actual value is a boolean TRUE
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

assert('$predicate->matches(true)');

?>
===DONE===
--EXPECT--
===DONE===