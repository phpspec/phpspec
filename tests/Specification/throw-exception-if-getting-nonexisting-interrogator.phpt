--TEST--
Should throw an Exception if we use the getInterrogator method before an Interrogator is created
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';


$spec = new PHPSpec_Specification;

try {
    $int = $spec->getInterrogator();
    assert(false);
} catch(Exception $e) {
    assert(true);
}

?>
===DONE===
--EXPECT--
===DONE===