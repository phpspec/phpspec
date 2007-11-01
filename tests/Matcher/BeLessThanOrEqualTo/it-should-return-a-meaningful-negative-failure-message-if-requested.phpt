--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$less = new PHPSpec_Matcher_BeLessThanOrEqualTo(0);
$less->matches(1);
assert('
$less->getNegativeFailureMessage() 
    == "expected 1 not to be less than or equal to 0 (using beLessThanOrEqualTo())"
');

?>
===DONE===
--EXPECT--
===DONE===