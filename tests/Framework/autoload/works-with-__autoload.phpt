--TEST--
Should work with __autoload() function if it is defined
--FILE--
<?php

assert('class_exists("PHPSpec_Expectation", true) == false');

function __autoload($class) {
    echo "__autoload called with: $class\n";
}

require_once dirname(__FILE__) . '/../../../src/PHPSpec/Framework.php';

assert('class_exists("PHPSpec_Expectation", true) == true');

?>
===DONE===
--EXPECT--
__autoload called with: PHPSpec_Expectation
===DONE===