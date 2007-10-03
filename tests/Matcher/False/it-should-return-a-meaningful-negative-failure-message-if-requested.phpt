--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$false = new PHPSpec_Matcher_False(false);
$false->matches(false);
assert('
$false->getNegativeFailureMessage() 
    == "expected TRUE or non-boolean not FALSE (using false())"
');

?>
===DONE===
--EXPECT--
===DONE===