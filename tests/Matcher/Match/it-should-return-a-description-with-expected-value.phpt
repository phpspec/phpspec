--TEST--
Should return a description of the expectation
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$match = new PHPSpec_Matcher_Match("/bar/");
$match->matches('bar');
echo $match->getDescription();

?>
--EXPECT--
match /bar/ PCRE regular expression