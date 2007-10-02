--TEST--
Should proxy member unset calls to the source object
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Object/Proxy.php';

class Foo {
    public $arg1 = null;
    public function __construct($arg1) {
        $this->arg1 = $arg1;
    }
}

$proxy = new PHPSpec_Object_Proxy('Foo', 1);
unset($proxy->arg1);
assert('!isset($proxy->arg1)');



?>
===DONE===
--EXPECT--
===DONE===