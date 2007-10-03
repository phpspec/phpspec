--TEST--
Should return a meaningful failure message if requested
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_True(true);
$true->matches(true);
assert('
$true->getNegativeFailureMessage() 
    == "expected FALSE or non-boolean not TRUE (using true())"
');

?>
===DONE===
--EXPECT--
===DONE===