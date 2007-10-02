--TEST--
Should construct source object with any optional arguments
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Object/Proxy.php';

class Foo {
    private $arg1 = null;
    public function __construct($arg1) {
        $this->arg1 = $arg1;
    }
    public function getArg1() {
        return $this->arg1;
    }
}

$proxy = new PHPSpec_Object_Proxy('Foo', 1);
assert('is_a($proxy->getSourceObject(), "Foo")');



?>
===DONE===
--EXPECT--
===DONE===