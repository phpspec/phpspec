--TEST--
Should ignore requests to load files that begin in something other than PHPSpec_
--FILE--
<?php

require_once dirname(__FILE__) . '/../../../src/PHPSpec/Framework.php';

assert('class_exists("PEAR_Registry", true) === false');

?>
===DONE===
--EXPECT--
===DONE===