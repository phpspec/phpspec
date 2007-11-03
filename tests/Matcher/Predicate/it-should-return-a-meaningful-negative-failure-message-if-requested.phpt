--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock {
    public function hasAClue() {
        return true;
    }
}

$predicate = new PHPSpec_Matcher_Predicate(false);
$predicate->setMethodName('hasAClue');
$predicate->setObject(new Mock);
$predicate->setPredicateCall('haveAClue');

$predicate->matches(true);

assert('
$predicate->getNegativeFailureMessage() 
    == "expected FALSE or non-boolean not TRUE (using haveAClue())"
');

?>
===DONE===
--EXPECT--
===DONE===