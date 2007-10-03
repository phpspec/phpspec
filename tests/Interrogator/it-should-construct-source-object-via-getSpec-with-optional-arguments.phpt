--TEST--
Should construct source object with any optional arguments
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Foo {
    private $arg1 = null;
    public function __construct($arg1) {
        $this->arg1 = $arg1;
    }
    public function getArg1() {
        return $this->arg1;
    }
}

$proxy = new PHPSpec_Object_Interrogator('Foo', 1);
assert('is_a($proxy->getSourceObject(), "Foo")');



?>
===DONE===
--EXPECT--
===DONE===