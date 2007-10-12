--TEST--
Should be able to count the Collection's specs
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock extends PHPSpec_Runner_Collection {
    public function __construct() {}
    public function count() { return 2; }
}

$base = new PHPSpec_Runner_Base(new Mock);

assert('count($base) == 2');

?>
===DONE===
--EXPECT--
===DONE===