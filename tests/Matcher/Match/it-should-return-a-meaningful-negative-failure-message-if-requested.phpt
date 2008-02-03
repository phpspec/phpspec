--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$null = new PHPSpec_Matcher_Match("/bar/");
$null->matches("bar");
assert('
$null->getNegativeFailureMessage() 
    == "expected no match for /bar/ PCRE regular expression, got bar (using match())"
');

?>
===DONE===
--EXPECT--
===DONE===