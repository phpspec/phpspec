--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$match = new PHPSpec_Matcher_Match("/bar/");
$match->matches('foo');
echo $match->getFailureMessage();

?>
--EXPECT--
expected match for /bar/ PCRE regular expression, got foo (using match())