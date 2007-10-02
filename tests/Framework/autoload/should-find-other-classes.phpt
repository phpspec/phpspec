--TEST--
Should be able to find unknown classes and load them
--FILE--
<?php

assert('class_exists("PHPSpec_Expectation", true) == false');

require_once dirname(__FILE__) . '/../../../src/PHPSpec/Framework.php';

assert('class_exists("PHPSpec_Expectation", true) == true');


?>
===DONE===
--EXPECT--
===DONE===