--TEST--
getCurrentSpecification method throws an exception if not preceded by a spec() call to create a PHPSpec_Specification object
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
}

$context = new describeBoo;

try {
    $context->getCurrentSpecification();
    assert(false);
} catch(PHPSpec_Exception $e) {
    assert(true);
}

?>
===DONE===
--EXPECT--
===DONE===