--TEST--
Should have a static function to execute a Collection without errors and return a self instance
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock extends PHPSpec_Runner_Collection {
    public function __construct() {}
    public function count() { return 0; }
    public function execute(PHPSpec_Runner_Result $result) {}
}

class Mock2 extends PHPSpec_Runner_Result {
    public function __construct() {}
    public function __toString() { return ''; }
}

$base = PHPSpec_Runner_Base::execute(new Mock, new Mock2);

assert('$base instanceof PHPSpec_Runner_Base');

?>
===DONE===
--EXPECT--
===DONE===