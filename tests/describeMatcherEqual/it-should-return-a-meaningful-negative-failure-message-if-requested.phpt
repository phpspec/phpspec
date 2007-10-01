--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/Equal.php';

$equal = new PHPSpec_Matcher_Equal(1);
$equal->matches(0);
assert('
$equal->getNegativeFailureMessage() 
    == "expected 0 not to equal 1 (using equal())"
');

?>
===DONE===
--EXPECT--
===DONE===