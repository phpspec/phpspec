--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/Equal.php';

$equal = new PHPSpec_Matcher_Equal(1);
$equal->matches(0);
assert('$equal->getFailureMessage() == "expected 1, got 0 (using equal())"');

?>
===DONE===
--EXPECT--
===DONE===