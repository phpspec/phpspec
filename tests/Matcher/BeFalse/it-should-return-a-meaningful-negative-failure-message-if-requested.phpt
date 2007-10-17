--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_BeFalse(false);
$false->matches(false);
assert('
$false->getNegativeFailureMessage() 
    == "expected TRUE or non-boolean not FALSE (using beFalse())"
');

?>
===DONE===
--EXPECT--
===DONE===