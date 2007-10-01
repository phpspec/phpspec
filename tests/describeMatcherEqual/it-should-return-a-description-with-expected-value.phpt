--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Matcher/Equal.php';

$equal = new PHPSpec_Matcher_Equal(1);
$equal->matches(0);
echo $equal->getDescription();

?>
--EXPECT--
equal 1